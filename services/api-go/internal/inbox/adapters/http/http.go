// Package http serves POST /api/webhooks/resend, the off-contract endpoint
// Resend posts webhook events to, as PHP's ResendWebhookController behind
// VerifyResendWebhookSignature: every request must carry a valid Svix
// signature (403 otherwise, and always while no secret is configured); an
// email.received event with an email id is queued for forwarding; every
// signed request is answered 204.
package http

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
)

// Path is the route, outside /api/v1 as in PHP.
const Path = "/api/webhooks/resend"

// DefaultTolerance is how far a signature's timestamp may be from now
// (RESEND_WEBHOOK_TOLERANCE in PHP).
const DefaultTolerance = 300 * time.Second

// maxBody caps the body read; Resend's events carry metadata only.
const maxBody = 1 << 20

// Receiver queues a received email for forwarding.
type Receiver interface {
	Receive(ctx context.Context, emailID string) error
}

// Config configures the handler.
type Config struct {
	// Secret is RESEND_WEBHOOK_SECRET, "whsec_" plus base64. Blank rejects
	// every request.
	Secret string
	// Tolerance defaults to DefaultTolerance.
	Tolerance time.Duration
	Clock     clock.Clock
	// WriteError writes an error as a Problem (httpapi.Responder.Error).
	WriteError func(http.ResponseWriter, *http.Request, error)
}

// Handler is the webhook endpoint.
type Handler struct {
	receiver Receiver
	cfg      Config
	key      []byte
}

// New returns the Handler; it fails on a secret that is not valid base64.
func New(receiver Receiver, cfg Config) (*Handler, error) {
	key, err := DecodeSecret(cfg.Secret)
	if err != nil {
		return nil, err
	}

	if cfg.Tolerance == 0 {
		cfg.Tolerance = DefaultTolerance
	}

	return &Handler{receiver: receiver, cfg: cfg, key: key}, nil
}

// DecodeSecret returns the signing key in a "whsec_<base64>" secret; a blank
// secret is a nil key.
func DecodeSecret(secret string) ([]byte, error) {
	if secret == "" {
		return nil, nil
	}

	key, err := base64.StdEncoding.DecodeString(strings.TrimPrefix(secret, "whsec_"))
	if err != nil || len(key) == 0 {
		return nil, errors.New("RESEND_WEBHOOK_SECRET must be whsec_ followed by base64")
	}

	return key, nil
}

type event struct {
	Type string `json:"type"`
	Data struct {
		EmailID string `json:"email_id"`
	} `json:"data"`
}

// ServeHTTP implements http.Handler.
func (h *Handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if len(h.key) == 0 {
		h.cfg.WriteError(w, r, apperr.Forbidden(apperr.CodeForbidden, "Webhook secret is not configured."))

		return
	}

	// A body cut at maxBody fails the signature check.
	body, err := io.ReadAll(io.LimitReader(r.Body, maxBody))
	if err != nil {
		h.cfg.WriteError(w, r, apperr.BadRequest("The request body could not be read.", err))

		return
	}

	if problem := verify(h.key, r.Header, body, h.cfg.Clock.Now(), h.cfg.Tolerance); problem != "" {
		h.cfg.WriteError(w, r, apperr.Forbidden(apperr.CodeForbidden, problem))

		return
	}

	// Anything but an email.received event with a string id, malformed JSON
	// included, is acknowledged and ignored, as in PHP.
	var e event
	if json.Unmarshal(body, &e) == nil && e.Type == "email.received" && e.Data.EmailID != "" {
		if err := h.receiver.Receive(r.Context(), e.Data.EmailID); err != nil {
			h.cfg.WriteError(w, r, fmt.Errorf("receive inbound email: %w", err))

			return
		}
	}

	w.WriteHeader(http.StatusNoContent)
}

// verify checks a Svix signature as Resend's PHP WebhookSignature::verify
// does, returning its failure message, or "" when the signature is valid:
// HMAC-SHA256 over "{svix-id}.{svix-timestamp}.{body}" with key, base64, as
// one of the space-separated "v1,<signature>" values of svix-signature, and a
// timestamp within tolerance of now.
func verify(key []byte, header http.Header, body []byte, now time.Time, tolerance time.Duration) string {
	id, timestamp, signatures := header.Get("Svix-Id"), header.Get("Svix-Timestamp"), header.Get("Svix-Signature")
	if id == "" || timestamp == "" || signatures == "" {
		return "Missing required headers"
	}

	seconds, err := strconv.ParseInt(timestamp, 10, 64)
	if err != nil {
		return "Invalid timestamp"
	}

	switch at := time.Unix(seconds, 0); {
	case at.Before(now.Add(-tolerance)):
		return "Message timestamp too old"
	case at.After(now.Add(tolerance)):
		return "Message timestamp too new"
	}

	mac := hmac.New(sha256.New, key)
	_, _ = fmt.Fprintf(mac, "%s.%d.", id, seconds)
	_, _ = mac.Write(body)
	expected := []byte(base64.StdEncoding.EncodeToString(mac.Sum(nil)))

	for versioned := range strings.SplitSeq(signatures, " ") {
		version, signature, _ := strings.Cut(versioned, ",")
		if version == "v1" && hmac.Equal([]byte(signature), expected) {
			return ""
		}
	}

	return "No signatures found matching the expected signature"
}

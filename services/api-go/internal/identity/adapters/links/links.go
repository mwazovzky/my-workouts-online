// Package links builds the links identity's emails carry. Verification
// links are signed with HMAC-SHA256 over "verify|{id}|{hash}|{expires}"
// with LINK_SIGNING_KEY, where hash is hex SHA-256 of the lowercased
// email; the SPA posts the four values back (ADR 0008). Reset links carry
// the opaque reset token and the email.
package links

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"net/url"
	"strconv"
	"strings"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
)

// Links implements ports.Links.
type Links struct {
	frontend string
	key      []byte
}

var _ ports.Links = Links{}

// New returns Links pointing at the SPA at frontendURL (FRONTEND_URL) and
// signing with key (LINK_SIGNING_KEY).
func New(frontendURL, key string) Links {
	return Links{frontend: strings.TrimRight(frontendURL, "/"), key: []byte(key)}
}

// VerificationURL implements ports.Links:
// {FRONTEND_URL}/verify-email/{id}/{hash}?expires={unix}&signature={hex}.
func (l Links) VerificationURL(userID int64, email string, expires time.Time) string {
	hash := l.EmailHash(email)
	unix := expires.Unix()

	query := url.Values{}
	query.Set("expires", strconv.FormatInt(unix, 10))
	query.Set("signature", l.sign(userID, hash, unix))

	return l.frontend + "/verify-email/" + strconv.FormatInt(userID, 10) + "/" + hash + "?" + query.Encode()
}

// VerificationValid implements ports.Links with a constant-time comparison.
func (l Links) VerificationValid(id int64, hash string, expires int64, signature string) bool {
	given, err := hex.DecodeString(signature)
	if err != nil {
		return false
	}

	want, _ := hex.DecodeString(l.sign(id, hash, expires))

	return hmac.Equal(given, want)
}

// EmailHash implements ports.Links.
func (l Links) EmailHash(email string) string {
	sum := sha256.Sum256([]byte(strings.ToLower(email)))

	return hex.EncodeToString(sum[:])
}

// ResetURL implements ports.Links: {FRONTEND_URL}/reset-password/{token}?email={email}.
func (l Links) ResetURL(token, email string) string {
	return l.frontend + "/reset-password/" + url.PathEscape(token) + "?" + url.Values{"email": {email}}.Encode()
}

func (l Links) sign(id int64, hash string, expires int64) string {
	mac := hmac.New(sha256.New, l.key)
	mac.Write([]byte("verify|" + strconv.FormatInt(id, 10) + "|" + hash + "|" + strconv.FormatInt(expires, 10)))

	return hex.EncodeToString(mac.Sum(nil))
}

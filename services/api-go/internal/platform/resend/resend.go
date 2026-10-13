// Package resend is a client for the three Resend API calls the service
// makes: sending an email (it implements kernel/mail.Mailer), and reading a
// received email and its attachments for the support inbox. It is written on
// net/http rather than resend-go so a failed call returns an *APIError with
// the HTTP status, which the inbox worker needs to tell a missing email
// (cancel) from an outage (retry); see ADR 0013.
package resend

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
)

// DefaultBaseURL is the Resend API.
const DefaultBaseURL = "https://api.resend.com/"

const (
	// apiTimeout bounds one API call.
	apiTimeout = 15 * time.Second
	// downloadTimeout bounds one attachment download, as PHP's Http::timeout(30).
	downloadTimeout = 30 * time.Second
	// maxDownload caps an attachment; Resend accepts at most 40 MB per email.
	maxDownload = 40 << 20
)

// Config configures a Client.
type Config struct {
	// BaseURL is the API root; DefaultBaseURL when empty. Tests point it at
	// an httptest server.
	BaseURL string
	// APIKey is RESEND_KEY.
	APIKey string
	// From is the sender of every message (MAIL_FROM_ADDRESS, MAIL_FROM_NAME).
	From mail.Address
}

// Client calls the Resend API.
type Client struct {
	baseURL  *url.URL
	apiKey   string
	from     mail.Address
	api      *http.Client
	download *http.Client
}

var _ mail.Mailer = (*Client)(nil)

// New returns a Client; it fails only on an unparsable BaseURL.
func New(cfg Config) (*Client, error) {
	raw := cfg.BaseURL
	if raw == "" {
		raw = DefaultBaseURL
	}

	base, err := url.Parse(raw)
	if err != nil {
		return nil, fmt.Errorf("resend: base URL: %w", err)
	}

	// Paths resolve against the base, so it must end with a slash.
	if base.Path == "" || base.Path[len(base.Path)-1] != '/' {
		base.Path += "/"
	}

	return &Client{
		baseURL:  base,
		apiKey:   cfg.APIKey,
		from:     cfg.From,
		api:      &http.Client{Timeout: apiTimeout},
		download: &http.Client{Timeout: downloadTimeout},
	}, nil
}

// APIError is a non-2xx answer from the Resend API.
type APIError struct {
	Status  int    `json:"-"`
	Name    string `json:"name"`
	Message string `json:"message"`
}

func (e *APIError) Error() string {
	return fmt.Sprintf("resend: %d %s: %s", e.Status, e.Name, e.Message)
}

// IsNotFound reports whether err is a 404 from the Resend API.
func IsNotFound(err error) bool {
	var apiErr *APIError

	return errors.As(err, &apiErr) && apiErr.Status == http.StatusNotFound
}

type sendRequest struct {
	From        string           `json:"from"`
	To          []string         `json:"to"`
	Subject     string           `json:"subject"`
	HTML        string           `json:"html,omitempty"`
	Text        string           `json:"text,omitempty"`
	ReplyTo     []string         `json:"reply_to,omitempty"`
	Attachments []sendAttachment `json:"attachments,omitempty"`
}

type sendAttachment struct {
	Filename string `json:"filename"`
	// Content is base64.
	Content     string `json:"content"`
	ContentType string `json:"content_type,omitempty"`
}

// Send implements mail.Mailer: POST /emails.
func (c *Client) Send(ctx context.Context, msg mail.Message) error {
	from := c.from
	if msg.FromName != "" {
		from.Name = msg.FromName
	}

	body := sendRequest{
		From: from.String(), To: []string{msg.To}, Subject: msg.Subject, HTML: msg.HTML, Text: msg.Text,
	}

	for _, address := range msg.ReplyTo {
		body.ReplyTo = append(body.ReplyTo, address.String())
	}

	for _, attachment := range msg.Attachments {
		body.Attachments = append(body.Attachments, sendAttachment{
			Filename:    attachment.Filename,
			Content:     base64.StdEncoding.EncodeToString(attachment.Content),
			ContentType: attachment.ContentType,
		})
	}

	return c.call(ctx, http.MethodPost, "emails", body, nil)
}

// ReceivedEmail is an email received on the domain (GET /emails/receiving/{id}).
type ReceivedEmail struct {
	ID        string   `json:"id"`
	From      string   `json:"from"`
	To        []string `json:"to"`
	Cc        []string `json:"cc"`
	ReplyTo   []string `json:"reply_to"`
	CreatedAt string   `json:"created_at"`
	Subject   string   `json:"subject"`
	HTML      string   `json:"html"`
	Text      string   `json:"text"`
	// Attachments lists the attachments without their content or URL.
	Attachments []struct {
		ID       string `json:"id"`
		Filename string `json:"filename"`
	} `json:"attachments"`
}

// ReceivedEmail fetches a received email.
func (c *Client) ReceivedEmail(ctx context.Context, id string) (ReceivedEmail, error) {
	var email ReceivedEmail
	err := c.call(ctx, http.MethodGet, "emails/receiving/"+id, nil, &email)

	return email, err
}

// ReceivedAttachment is an attachment with a short-lived download URL.
type ReceivedAttachment struct {
	ID          string `json:"id"`
	Filename    string `json:"filename"`
	ContentType string `json:"content_type"`
	DownloadURL string `json:"download_url"`
}

// ReceivedAttachments lists a received email's attachments
// (GET /emails/receiving/{id}/attachments).
func (c *Client) ReceivedAttachments(ctx context.Context, emailID string) ([]ReceivedAttachment, error) {
	var list struct {
		Data []ReceivedAttachment `json:"data"`
	}

	err := c.call(ctx, http.MethodGet, "emails/receiving/"+emailID+"/attachments", nil, &list)

	return list.Data, err
}

// Download fetches an attachment's content from its download URL. The URL is
// pre-signed and on another host, so the API key is not sent.
func (c *Client) Download(ctx context.Context, downloadURL string) ([]byte, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, downloadURL, http.NoBody)
	if err != nil {
		return nil, fmt.Errorf("resend: download: %w", err)
	}

	resp, err := c.download.Do(req)
	if err != nil {
		return nil, fmt.Errorf("resend: download: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{Status: resp.StatusCode, Name: "download_failed", Message: resp.Status}
	}

	content, err := io.ReadAll(io.LimitReader(resp.Body, maxDownload+1))
	if err != nil {
		return nil, fmt.Errorf("resend: download: %w", err)
	}

	if len(content) > maxDownload {
		return nil, fmt.Errorf("resend: download: attachment larger than %d bytes", maxDownload)
	}

	return content, nil
}

// call sends one API request with the key and decodes a 2xx JSON answer into
// out (when not nil); any other status is an *APIError. path is unescaped;
// JoinPath escapes it.
func (c *Client) call(ctx context.Context, method, path string, in, out any) error {
	var body io.Reader = http.NoBody

	if in != nil {
		data, err := json.Marshal(in)
		if err != nil {
			return fmt.Errorf("resend: encode: %w", err)
		}

		body = bytes.NewReader(data)
	}

	req, err := http.NewRequestWithContext(ctx, method, c.baseURL.JoinPath(path).String(), body)
	if err != nil {
		return fmt.Errorf("resend: %w", err)
	}

	req.Header.Set("Authorization", "Bearer "+c.apiKey)
	req.Header.Set("Accept", "application/json")

	if in != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	resp, err := c.api.Do(req)
	if err != nil {
		return fmt.Errorf("resend: %s %s: %w", method, path, err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode > 299 {
		apiErr := &APIError{Status: resp.StatusCode}
		// Resend answers {"statusCode", "name", "message"}; a body that is not
		// JSON leaves the status line as the message.
		if json.NewDecoder(io.LimitReader(resp.Body, 64<<10)).Decode(apiErr) != nil || apiErr.Message == "" {
			apiErr.Message = resp.Status
		}

		return apiErr
	}

	if out == nil {
		return nil
	}

	if err := json.NewDecoder(resp.Body).Decode(out); err != nil {
		return fmt.Errorf("resend: decode %s %s: %w", method, path, err)
	}

	return nil
}

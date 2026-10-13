package http

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"fmt"
	"net/http"
	"testing"
	"time"
)

// The secret of PHP's ResendWebhookTest.
const testSecret = "whsec_dGVzdC13ZWJob29rLXNlY3JldC0xMjM0NTY3OA=="

// sign returns the svix-signature value Resend computes for message msg_1.
func sign(t *testing.T, timestamp int64, body string) string {
	t.Helper()

	key, err := DecodeSecret(testSecret)
	if err != nil {
		t.Fatal(err)
	}

	mac := hmac.New(sha256.New, key)
	_, _ = fmt.Fprintf(mac, "msg_1.%d.%s", timestamp, body)

	return "v1," + base64.StdEncoding.EncodeToString(mac.Sum(nil))
}

func TestVerify(t *testing.T) {
	t.Parallel()

	key, err := DecodeSecret(testSecret)
	if err != nil {
		t.Fatal(err)
	}

	now := time.Date(2026, 10, 5, 20, 0, 0, 0, time.UTC)
	body := `{"type":"email.received"}`
	valid := sign(t, now.Unix(), body)

	tests := []struct {
		name      string
		id        string
		timestamp string
		signature string
		want      string
	}{
		{"valid", "msg_1", fmt.Sprint(now.Unix()), valid, ""},
		{"valid among several, after other versions", "msg_1", fmt.Sprint(now.Unix()), "v2,abc v1,Zm9yZ2Vk " + valid, ""},
		{"at the edge of the tolerance", "msg_1", fmt.Sprint(now.Unix() - 300), sign(t, now.Unix()-300, body), ""},
		{"forged", "msg_1", fmt.Sprint(now.Unix()), "v1," + base64.StdEncoding.EncodeToString([]byte("forged")), "No signatures found matching the expected signature"},
		{"signed for another message id", "msg_2", fmt.Sprint(now.Unix()), valid, "No signatures found matching the expected signature"},
		{"the right signature under another version", "msg_1", fmt.Sprint(now.Unix()), "v1a" + valid[2:], "No signatures found matching the expected signature"},
		{"missing id", "", fmt.Sprint(now.Unix()), valid, "Missing required headers"},
		{"missing timestamp", "msg_1", "", valid, "Missing required headers"},
		{"missing signature", "msg_1", fmt.Sprint(now.Unix()), "", "Missing required headers"},
		{"timestamp not a number", "msg_1", "soon", valid, "Invalid timestamp"},
		{"stale", "msg_1", fmt.Sprint(now.Unix() - 3600), sign(t, now.Unix()-3600, body), "Message timestamp too old"},
		{"from the future", "msg_1", fmt.Sprint(now.Unix() + 301), sign(t, now.Unix()+301, body), "Message timestamp too new"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			header := http.Header{}
			for name, value := range map[string]string{"svix-id": tt.id, "svix-timestamp": tt.timestamp, "svix-signature": tt.signature} {
				if value != "" {
					header.Set(name, value)
				}
			}

			if got := verify(key, header, []byte(body), now, DefaultTolerance); got != tt.want {
				t.Errorf("verify() = %q, want %q", got, tt.want)
			}
		})
	}
}

func TestDecodeSecret(t *testing.T) {
	t.Parallel()

	if key, err := DecodeSecret(""); key != nil || err != nil {
		t.Errorf("blank secret = %v, %v; want no key and no error", key, err)
	}

	if key, err := DecodeSecret(testSecret); err != nil || string(key) != "test-webhook-secret-12345678" {
		t.Errorf("DecodeSecret() = %q, %v", key, err)
	}

	if key, err := DecodeSecret("dGVzdA=="); err != nil || string(key) != "test" {
		t.Errorf("a secret without the prefix = %q, %v", key, err)
	}

	for _, bad := range []string{"whsec_not base64!", "whsec_"} {
		if _, err := DecodeSecret(bad); err == nil {
			t.Errorf("DecodeSecret(%q) error = nil", bad)
		}
	}
}

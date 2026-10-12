package links_test

import (
	"net/url"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/links"
)

var expires = time.Date(2026, 10, 12, 10, 0, 0, 0, time.UTC)

type posted struct {
	id        int64
	hash      string
	expires   int64
	signature string
}

// parse reads a verification URL the way the SPA's VerifyEmailLink page does.
func parse(t *testing.T, raw string) posted {
	t.Helper()

	u, err := url.Parse(raw)
	if err != nil {
		t.Fatal(err)
	}

	segments := strings.Split(strings.TrimPrefix(u.Path, "/"), "/")
	if len(segments) != 3 || segments[0] != "verify-email" {
		t.Fatalf("path = %q", u.Path)
	}

	id, _ := strconv.ParseInt(segments[1], 10, 64)
	exp, _ := strconv.ParseInt(u.Query().Get("expires"), 10, 64)

	return posted{id: id, hash: segments[2], expires: exp, signature: u.Query().Get("signature")}
}

func TestVerificationURLRoundTrips(t *testing.T) {
	t.Parallel()

	l := links.New("https://app.example.test/", "key")
	raw := l.VerificationURL(7, "Ada@Example.com", expires)

	if !strings.HasPrefix(raw, "https://app.example.test/verify-email/7/"+l.EmailHash("ada@example.com")+"?expires=") {
		t.Errorf("url = %q, want the SPA page with the lowercased email's hash", raw)
	}

	p := parse(t, raw)
	if p.expires != expires.Unix() || !l.VerificationValid(p.id, p.hash, p.expires, p.signature) {
		t.Errorf("posted back %+v does not verify", p)
	}
}

func TestTamperedLinksFail(t *testing.T) {
	t.Parallel()

	l := links.New("https://app.example.test", "key")
	p := parse(t, l.VerificationURL(7, "ada@example.com", expires))

	tests := []struct {
		name string
		p    posted
	}{
		{"other user", posted{8, p.hash, p.expires, p.signature}},
		{"other email", posted{p.id, l.EmailHash("bea@example.com"), p.expires, p.signature}},
		{"later expiry", posted{p.id, p.hash, p.expires + 3600, p.signature}},
		{"flipped signature", posted{p.id, p.hash, p.expires, strings.Repeat("0", len(p.signature))}},
		{"not hex", posted{p.id, p.hash, p.expires, "zz"}},
	}

	for _, tt := range tests {
		if l.VerificationValid(tt.p.id, tt.p.hash, tt.p.expires, tt.p.signature) {
			t.Errorf("%s: verified", tt.name)
		}
	}

	if links.New("https://app.example.test", "other key").VerificationValid(p.id, p.hash, p.expires, p.signature) {
		t.Error("a link verified under another key")
	}
}

func TestResetURL(t *testing.T) {
	t.Parallel()

	got := links.New("https://app.example.test", "key").ResetURL("abc123", "ada+x@example.com")
	if want := "https://app.example.test/reset-password/abc123?email=ada%2Bx%40example.com"; got != want {
		t.Errorf("ResetURL() = %q, want %q", got, want)
	}
}

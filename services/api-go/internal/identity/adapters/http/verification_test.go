package http_test

import (
	"net/http"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/EmailVerificationTest.php and
// RegisterTest::unverified_user_is_blocked_from_verified_routes_with_a_code.

func (f *fixture) verify(token string, body map[string]any) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/email/verify", Token: token, Body: body})
}

func (f *fixture) verifiedAt(t *testing.T, email string) bool {
	t.Helper()

	return f.count(t, "SELECT count(*) FROM users WHERE email = $1 AND email_verified_at IS NOT NULL", email) == 1
}

func TestVerificationLinkPointsAtTheFrontend(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	user := f.register(t, "ada@example.com").User

	link := f.lastLink(t, "ada@example.com").String()
	if prefix := "https://app.example.test/verify-email/" + itoa(user.ID) + "/"; !strings.HasPrefix(link, prefix) || !strings.Contains(link, "?expires=") {
		t.Errorf("link = %q, want it under %s with expires and signature", link, prefix)
	}

	if subject := f.mail.Sent("ada@example.com")[0].Subject; subject != "Verify Email Address" {
		t.Errorf("subject = %q", subject)
	}
}

func TestVerificationEmailFollowsTheRequestLocale(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/register", Locale: "ru", Body: map[string]any{
		"name": "Ada", "email": "ada@example.com", "password": password, "client": "native",
	}}).AssertStatus(http.StatusCreated)

	if subject := f.mail.Sent("ada@example.com")[0].Subject; subject != "Подтвердите адрес электронной почты" {
		t.Errorf("subject = %q, want the Russian one", subject)
	}
}

func TestPostingTheLinkParametersVerifiesTheEmail(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	// Unverified: verified-only operations are refused, the account is not.
	if code := problemCode(t, f.programs(session.AccessToken).AssertStatus(http.StatusForbidden)); code != "email_unverified" {
		t.Errorf("before verifying: code = %q, want email_unverified", code)
	}

	f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/me", Token: session.AccessToken}).AssertStatus(http.StatusOK)

	f.verify(session.AccessToken, verifyBody(t, f.lastLink(t, "ada@example.com"))).AssertStatus(http.StatusNoContent)

	if !f.verifiedAt(t, "ada@example.com") {
		t.Error("the email is not verified")
	}

	f.programs(session.AccessToken).AssertStatus(http.StatusNotImplemented)
}

func TestLinkForAnotherAccountIsRefused(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	ada := f.register(t, "ada@example.com")
	f.register(t, "bea@example.com")

	res := f.verify(ada.AccessToken, verifyBody(t, f.lastLink(t, "bea@example.com"))).AssertStatus(http.StatusForbidden)
	if code := problemCode(t, res); code != "invalid_verification_link" {
		t.Errorf("code = %q", code)
	}

	if f.verifiedAt(t, "ada@example.com") || f.verifiedAt(t, "bea@example.com") {
		t.Error("a link for another account verified someone")
	}
}

func TestTamperedOrExpiredSignatureIsRefused(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")
	body := verifyBody(t, f.lastLink(t, "ada@example.com"))

	tampered := map[string]map[string]any{
		"signature": with(body, "signature", strings.Repeat("0", 64)),
		"expires":   with(body, "expires", body["expires"].(int)+3600),
	}

	for name, b := range tampered {
		if code := problemCode(t, f.verify(session.AccessToken, b).AssertStatus(http.StatusForbidden)); code != "invalid_signature" {
			t.Errorf("tampered %s: code = %q", name, code)
		}
	}

	// The id and hash are checked against the account first.
	for _, field := range []string{"id", "hash"} {
		changed := with(body, field, map[string]any{"id": 999, "hash": "beef"}[field])
		if code := problemCode(t, f.verify(session.AccessToken, changed).AssertStatus(http.StatusForbidden)); code != "invalid_verification_link" {
			t.Errorf("tampered %s: code = %q", field, code)
		}
	}

	f.clock.Advance(2 * time.Hour)

	fresh := decodeAuth(t, f.login("ada@example.com", password, "native", nil)).AccessToken
	if code := problemCode(t, f.verify(fresh, body).AssertStatus(http.StatusForbidden)); code != "invalid_signature" {
		t.Errorf("expired: code = %q", code)
	}

	if f.verifiedAt(t, "ada@example.com") {
		t.Error("a bad link verified the email")
	}
}

func TestVerifyIsIdempotentForVerifiedUsers(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.verified(t, "ada@example.com")

	f.verify(session.AccessToken, verifyBody(t, f.lastLink(t, "ada@example.com"))).AssertStatus(http.StatusNoContent)
}

func TestResendAnswers202AndSendsOnlyForUnverified(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	unverified := f.register(t, "ada@example.com")
	verified := f.verified(t, "bea@example.com")

	resend := func(token string) {
		f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/email/resend", Token: token}).AssertStatus(http.StatusAccepted)
	}

	resend(unverified.AccessToken)
	resend(verified.AccessToken)

	if n := len(f.mail.Sent("ada@example.com")); n != 2 {
		t.Errorf("emails to the unverified user = %d, want registration + resend", n)
	}

	if n := len(f.mail.Sent("bea@example.com")); n != 1 {
		t.Errorf("emails to the verified user = %d, want only the registration one", n)
	}
}

func TestVerifyRequiresAuthentication(t *testing.T) {
	t.Parallel()

	newFixture(t).verify("", map[string]any{"id": 1, "hash": "x", "expires": 1, "signature": "y"}).
		AssertStatus(http.StatusUnauthorized)
}

func TestVerifyIsThrottledToSixAMinute(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken
	bad := map[string]any{"id": 999, "hash": "x", "expires": 1, "signature": "y"}

	for range 6 {
		f.verify(token, bad).AssertStatus(http.StatusForbidden)
	}

	f.verify(token, bad).AssertStatus(http.StatusTooManyRequests)
}

func with(m map[string]any, key string, value any) map[string]any {
	out := map[string]any{}
	for k, v := range m {
		out[k] = v
	}

	out[key] = value

	return out
}

func itoa(n int64) string {
	return strconv.FormatInt(n, 10)
}

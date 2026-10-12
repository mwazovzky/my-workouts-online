package http_test

import (
	"errors"
	"net/http"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/PasswordResetTest.php.

func (f *fixture) forgot(email string) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/password/forgot", Body: map[string]any{"email": email}})
}

func (f *fixture) reset(body map[string]any) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/password/reset", Body: body})
}

// resetToken follows the newest reset email's link.
func (f *fixture) resetToken(t *testing.T, email string) string {
	t.Helper()

	link := f.lastLink(t, email)
	if !strings.HasPrefix(link.Path, "/reset-password/") || link.Query().Get("email") != email {
		t.Fatalf("not a reset link: %s", link)
	}

	return strings.TrimPrefix(link.Path, "/reset-password/")
}

func TestForgotSendsAResetLinkPointingAtTheFrontend(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "ada@example.com")

	f.forgot("ada@example.com").AssertStatus(http.StatusAccepted)

	sent := f.mail.Sent("ada@example.com")
	if len(sent) != 2 || sent[1].Subject != "Reset Password Notification" {
		t.Fatalf("emails = %+v, want the reset email after the verification one", sent)
	}

	if link := f.lastLink(t, "ada@example.com").String(); !strings.HasPrefix(link, "https://app.example.test/reset-password/") || !strings.HasSuffix(link, "?email=ada%40example.com") {
		t.Errorf("link = %q", link)
	}
}

func TestForgotDoesNotRevealUnknownEmails(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.forgot("nobody@example.com").AssertStatus(http.StatusAccepted)

	if n := len(f.mail.Sent("nobody@example.com")); n != 0 {
		t.Errorf("emails = %d, want none", n)
	}
}

func TestForgotSwallowsMailFailures(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "ada@example.com")
	f.mail.Err = errors.New("smtp down")

	f.forgot("ada@example.com").AssertStatus(http.StatusAccepted)
}

func TestResetUpdatesThePasswordAndRevokesEveryDevice(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")
	f.forgot("ada@example.com")

	f.reset(map[string]any{"token": f.resetToken(t, "ada@example.com"), "email": "ada@example.com", "password": "new-password-123"}).
		AssertStatus(http.StatusNoContent)

	f.login("ada@example.com", "new-password-123", "native", nil).AssertStatus(http.StatusOK)
	f.login("ada@example.com", password, "native", nil).AssertStatus(http.StatusUnauthorized)

	if code := problemCode(t, f.refresh(session.RefreshToken).AssertStatus(http.StatusUnauthorized)); code != "refresh_token_invalid" {
		t.Errorf("old refresh token: code = %q", code)
	}
}

func TestResetWithInvalidTokenFails(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "ada@example.com")
	f.forgot("ada@example.com")

	token := f.resetToken(t, "ada@example.com")
	body := map[string]any{"token": token, "email": "ada@example.com", "password": "new-password-123"}

	for name, b := range map[string]map[string]any{
		"bogus token": with(body, "token", "bogus"),
		"other email": with(body, "email", "bea@example.com"),
	} {
		res := f.reset(b).AssertStatus(http.StatusUnprocessableEntity)
		if got := fieldErrors(t, res); !slices.Equal(got, []string{"/token invalid_value"}) {
			t.Errorf("%s: errors = %v", name, got)
		}
	}

	f.clock.Advance(61 * time.Minute)

	if got := fieldErrors(t, f.reset(body).AssertStatus(http.StatusUnprocessableEntity)); !slices.Equal(got, []string{"/token invalid_value"}) {
		t.Errorf("expired: errors = %v", got)
	}
}

func TestAResetTokenWorksOnce(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "ada@example.com")
	f.forgot("ada@example.com")

	body := map[string]any{"token": f.resetToken(t, "ada@example.com"), "email": "ada@example.com", "password": "new-password-123"}
	f.reset(body).AssertStatus(http.StatusNoContent)
	f.reset(body).AssertStatus(http.StatusUnprocessableEntity)
}

func TestResetNoLongerAcceptsAConfirmationField(t *testing.T) {
	t.Parallel()

	res := newFixture(t).reset(map[string]any{
		"token": "bogus", "email": "ada@example.com", "password": "new-password-123", "password_confirmation": "new-password-123",
	}).AssertStatus(http.StatusUnprocessableEntity)

	if got := fieldErrors(t, res); !slices.Equal(got, []string{"/password_confirmation unknown_field"}) {
		t.Errorf("errors = %v", got)
	}
}

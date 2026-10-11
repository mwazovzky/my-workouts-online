package http_test

import (
	"net/http"
	"regexp"
	"slices"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/LoginTest.php.

func (f *fixture) login(email, pw, client string, extra map[string]any) *apitest.Response {
	body := map[string]any{"email": email, "password": pw, "client": client}
	for k, v := range extra {
		body[k] = v
	}

	return f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/login", Body: body})
}

func TestWebClientReceivesAccessTokenAndRefreshCookie(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	user := f.register(t, "ada@example.com").User

	res := f.login("ada@example.com", password, "web", nil).AssertStatus(http.StatusOK)

	body := decodeAuth(t, res)
	if body.AccessToken == "" || body.TokenType != "Bearer" || body.ExpiresIn != 900 || body.User.ID != user.ID || body.RefreshToken != "" {
		t.Errorf("body = %+v, want a bearer token, the user and no refresh token", body)
	}

	cookie := refreshCookie(t, res)
	if cookie == nil {
		t.Fatal("no refresh_token cookie")
	}

	if !cookie.HttpOnly || cookie.SameSite != http.SameSiteStrictMode || cookie.Path != "/api/v1/auth/refresh" || cookie.MaxAge != 2592000 {
		t.Errorf("cookie = %+v, want HttpOnly, SameSite=Strict, the refresh path and a 30-day Max-Age", cookie)
	}

	if n := f.count(t, "SELECT count(*) FROM refresh_tokens WHERE user_id = $1 AND token_hash = $2", user.ID, domain.HashToken(cookie.Value)); n != 1 {
		t.Errorf("stored tokens matching the cookie = %d, want 1", n)
	}
}

func TestNativeClientReceivesRefreshTokenInBodyAndNoCookie(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "ada@example.com")

	res := f.login("ada@example.com", password, "native", map[string]any{"device_name": "iPhone"}).AssertStatus(http.StatusOK)

	if decodeAuth(t, res).RefreshToken == "" || refreshCookie(t, res) != nil {
		t.Error("want the refresh token in the body and no cookie")
	}

	if n := f.count(t, "SELECT count(*) FROM refresh_tokens WHERE device_name = 'iPhone'"); n != 1 {
		t.Errorf("tokens for the device = %d, want 1", n)
	}
}

func TestAccessTokenAuthenticatesAPIRequests(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "ada@example.com")

	token := decodeAuth(t, f.login("ada@example.com", password, "native", nil)).AccessToken
	f.programs(token).AssertStatus(http.StatusNotImplemented)
}

func TestRejectsInvalidPasswordWith401(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "ada@example.com")
	before := f.count(t, "SELECT count(*) FROM refresh_tokens")

	p := f.login("ada@example.com", "wrong", "web", nil).AssertStatus(http.StatusUnauthorized).Problem()
	if p.Code != "invalid_credentials" || p.Detail != "These credentials do not match our records." || p.Errors != nil {
		t.Errorf("problem = %+v, want invalid_credentials without errors", p)
	}

	// An unknown account is answered the same way.
	if code := problemCode(t, f.login("nobody@example.com", password, "web", nil)); code != "invalid_credentials" {
		t.Errorf("unknown account: code = %q", code)
	}

	if after := f.count(t, "SELECT count(*) FROM refresh_tokens"); after != before {
		t.Errorf("refresh tokens %d → %d, want none issued", before, after)
	}
}

func TestRejectsMalformedEmailWith422(t *testing.T) {
	t.Parallel()

	res := newFixture(t).login("not-an-email", "x", "web", nil).AssertStatus(http.StatusUnprocessableEntity)
	if got := fieldErrors(t, res); !slices.Equal(got, []string{"/email invalid_format"}) {
		t.Errorf("errors = %v", got)
	}
}

func TestRequiresClientKind(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	missing := f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/login", Body: map[string]any{"email": "a@b.c", "password": "x"}}).
		AssertStatus(http.StatusUnprocessableEntity)
	if got := fieldErrors(t, missing); !slices.Equal(got, []string{"/client required"}) {
		t.Errorf("missing client: errors = %v", got)
	}

	desktop := f.login("a@b.c", "x", "desktop", nil).AssertStatus(http.StatusUnprocessableEntity)
	if got := fieldErrors(t, desktop); !slices.Equal(got, []string{"/client invalid_value"}) {
		t.Errorf("unknown client: errors = %v", got)
	}
}

func TestRateLimitsAfterFiveFailedAttempts(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "ada@example.com")

	for range 5 {
		f.login("Ada@Example.com", "wrong", "web", nil).AssertStatus(http.StatusUnauthorized)
		f.clock.Advance(time.Second)
	}

	res := f.login("ada@example.com", password, "web", nil).AssertStatus(http.StatusTooManyRequests)

	p := res.Problem()
	if p.Code != "too_many_attempts" || p.Errors != nil {
		t.Errorf("problem = %+v, want too_many_attempts", p)
	}

	if retry := res.Header().Get("Retry-After"); !regexp.MustCompile(`^\d+$`).MatchString(retry) || retry != "55" {
		t.Errorf("Retry-After = %q, want 55 (the window opened at the first failure)", retry)
	}

	if p.Detail != "Too many login attempts. Please try again in 55 seconds." {
		t.Errorf("detail = %q", p.Detail)
	}

	// Another IP is not locked out, and the lock lifts with the window.
	f.clock.Advance(55 * time.Second)
	f.login("ada@example.com", password, "web", nil).AssertStatus(http.StatusOK)
}

package http_test

import (
	"encoding/json"
	"net/http"
	"slices"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/ReauthTest.php.

func (f *fixture) reauth(token, pw string) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/reauth", Token: token, Body: map[string]any{"password": pw}})
}

func TestCorrectPasswordReturnsATokenWithFreshAuthTime(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")
	original := authTime(t, session.AccessToken)

	f.clock.Advance(30 * time.Minute)
	refreshed := decodeAuth(t, f.refresh(session.RefreshToken).AssertStatus(http.StatusOK))
	f.clock.Advance(5 * time.Minute)

	res := f.reauth(refreshed.AccessToken, password).AssertStatus(http.StatusOK)

	var body struct {
		AccessToken string `json:"access_token"`
		ExpiresIn   int    `json:"expires_in"`
	}
	if err := json.Unmarshal(res.Body.Bytes(), &body); err != nil || body.ExpiresIn != 900 {
		t.Fatalf("body = %s", res.Body)
	}

	fresh := authTime(t, body.AccessToken)
	if fresh <= original || fresh != f.clock.Now().Unix() {
		t.Errorf("auth_time = %d, want now (%d), later than %d", fresh, f.clock.Now().Unix(), original)
	}

	// The family keeps the new time: the next refresh carries it.
	again := decodeAuth(t, f.refresh(refreshed.RefreshToken).AssertStatus(http.StatusOK))
	if got := authTime(t, again.AccessToken); got != fresh {
		t.Errorf("auth_time after the next refresh = %d, want %d", got, fresh)
	}
}

func TestWrongPasswordIsRejected(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	res := f.reauth(token, "nope").AssertStatus(http.StatusUnprocessableEntity)
	if got := fieldErrors(t, res); !slices.Equal(got, []string{"/password mismatch"}) {
		t.Errorf("errors = %v", got)
	}
}

// PHP's test signs in with actingAs and no bearer token and gets 403; in Go
// a reauth without a bearer token never reaches the handler: 401.
func TestReauthWithoutABearerTokenIsUnauthenticated(t *testing.T) {
	t.Parallel()

	newFixture(t).reauth("", password).AssertStatus(http.StatusUnauthorized)
}

func TestReauthIsThrottledToSixAMinute(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	for range 6 {
		f.reauth(token, "nope").AssertStatus(http.StatusUnprocessableEntity)
	}

	res := f.reauth(token, password).AssertStatus(http.StatusTooManyRequests)
	if code := problemCode(t, res); code != "too_many_requests" || res.Header().Get("Retry-After") == "" {
		t.Errorf("code = %q, Retry-After %q", code, res.Header().Get("Retry-After"))
	}
}

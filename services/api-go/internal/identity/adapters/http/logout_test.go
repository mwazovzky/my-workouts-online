package http_test

import (
	"net/http"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/LogoutTest.php.

func TestLogoutRevokesTheCurrentFamilyAndClearsTheCookie(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	current := f.register(t, "ada@example.com")
	other := decodeAuth(t, f.login("ada@example.com", password, "native", map[string]any{"device_name": "phone"}))

	res := f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/logout", Token: current.AccessToken}).
		AssertStatus(http.StatusNoContent)

	cookie := refreshCookie(t, res)
	if cookie == nil || cookie.Value != "" || cookie.MaxAge >= 0 || cookie.Path != "/api/v1/auth/refresh" {
		t.Errorf("cookie = %+v, want the refresh cookie cleared", cookie)
	}

	if code := problemCode(t, f.refresh(current.RefreshToken).AssertStatus(http.StatusUnauthorized)); code != "refresh_token_invalid" {
		t.Errorf("refresh in the logged-out family: code = %q", code)
	}

	f.refresh(other.RefreshToken).AssertStatus(http.StatusOK)
}

func TestLogoutRequiresAuthentication(t *testing.T) {
	t.Parallel()

	res := newFixture(t).api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/logout"}).
		AssertStatus(http.StatusUnauthorized)

	if code := problemCode(t, res); code != "unauthenticated" {
		t.Errorf("code = %q, want unauthenticated", code)
	}
}

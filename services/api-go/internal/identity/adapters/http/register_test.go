package http_test

import (
	"net/http"
	"slices"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/RegisterTest.php;
// unverified_user_is_blocked_from_verified_routes_with_a_code is
// TestPostingTheLinkParametersVerifiesTheEmail.

func registerBody(overrides map[string]any) map[string]any {
	body := map[string]any{"name": "New User", "email": "new@example.com", "password": "password123", "client": "native"}
	for k, v := range overrides {
		body[k] = v
	}

	return body
}

func (f *fixture) registerWith(body map[string]any) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/register", Body: body})
}

func TestNativeClientRegistersAndReceivesTokens(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	body := decodeAuth(t, f.registerWith(registerBody(map[string]any{"device_name": "iPhone"})).AssertStatus(http.StatusCreated))

	if body.AccessToken == "" || body.RefreshToken == "" || body.ExpiresIn != 900 || body.User.Email != "new@example.com" || body.User.EmailVerifiedAt != nil {
		t.Errorf("body = %+v, want tokens for the new, unverified user", body)
	}

	if n := f.count(t, "SELECT count(*) FROM refresh_tokens WHERE user_id = $1 AND device_name = 'iPhone'", body.User.ID); n != 1 {
		t.Errorf("tokens for the device = %d, want 1", n)
	}
}

func TestTheNewUserCarriesTheDefaultLocaleAndTheme(t *testing.T) {
	t.Parallel()

	user := decodeAuth(t, newFixture(t).registerWith(registerBody(nil)).AssertStatus(http.StatusCreated)).User
	if user.Locale != "en" || user.ThemePreference != "system" || user.IsAdmin {
		t.Errorf("user = %+v, want locale en, theme system, not an admin", user)
	}
}

func TestWebClientReceivesRefreshCookie(t *testing.T) {
	t.Parallel()

	res := newFixture(t).registerWith(registerBody(map[string]any{"client": "web"})).AssertStatus(http.StatusCreated)

	if decodeAuth(t, res).RefreshToken != "" || refreshCookie(t, res) == nil {
		t.Error("want the refresh token in a cookie only")
	}
}

func TestRegisterValidatesInput(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	tests := []struct {
		name string
		body map[string]any
		want []string
	}{
		{"empty", map[string]any{}, []string{"/client required", "/email required", "/name required", "/password required"}},
		{"unknown field", registerBody(map[string]any{"password_confirmation": "password123"}), []string{"/password_confirmation unknown_field"}},
		{"uppercase email", registerBody(map[string]any{"email": "Upper@Example.com"}), []string{"/email invalid_format"}},
		{"non-string email", registerBody(map[string]any{"email": []any{map[string]any{"nested": 1}}}), []string{"/email invalid_format"}},
		{"short password", registerBody(map[string]any{"password": "short"}), []string{"/password too_short"}},
	}

	for _, tt := range tests {
		res := f.registerWith(tt.body).AssertStatus(http.StatusUnprocessableEntity)

		got := fieldErrors(t, res)
		slices.Sort(got)

		if !slices.Equal(got, tt.want) {
			t.Errorf("%s: errors = %v, want %v", tt.name, got, tt.want)
		}
	}
}

func TestRejectsDuplicateEmail(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "new@example.com")

	res := f.registerWith(registerBody(nil)).AssertStatus(http.StatusUnprocessableEntity)
	if got := fieldErrors(t, res); !slices.Equal(got, []string{"/email not_unique"}) {
		t.Errorf("errors = %v", got)
	}
}

func TestRegisterIsThrottledToTenAMinutePerClient(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	for range 10 {
		f.registerWith(map[string]any{}).AssertStatus(http.StatusUnprocessableEntity)
	}

	if code := problemCode(t, f.registerWith(registerBody(nil)).AssertStatus(http.StatusTooManyRequests)); code != "too_many_requests" {
		t.Errorf("code = %q", code)
	}
}

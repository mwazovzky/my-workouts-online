package http_test

import (
	"encoding/json"
	"net/http"
	"slices"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/MeApiTest.php.

type me struct {
	ID              int64   `json:"id"`
	Name            string  `json:"name"`
	Email           string  `json:"email"`
	Locale          string  `json:"locale"`
	ThemePreference string  `json:"theme_preference"`
	IsAdmin         bool    `json:"is_admin"`
	EmailVerifiedAt *string `json:"email_verified_at"`
}

func decodeMe(t *testing.T, res *apitest.Response) me {
	t.Helper()

	var body struct {
		Data me `json:"data"`
	}
	if err := json.Unmarshal(res.Body.Bytes(), &body); err != nil {
		t.Fatalf("not a UserResponse: %v\n%s", err, res.Body)
	}

	return body.Data
}

func (f *fixture) getMe(token string) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/me", Token: token})
}

func (f *fixture) patchMe(token string, body any) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodPatch, Path: "/api/v1/me", Token: token, Body: body})
}

func TestReturnsTheBearerUser(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.verified(t, "ada@example.com")

	got := decodeMe(t, f.getMe(session.AccessToken).AssertStatus(http.StatusOK))
	if got.ID != session.User.ID || got.Name != "Ada" || got.Email != "ada@example.com" || got.Locale != "en" ||
		got.ThemePreference != "system" || got.IsAdmin || got.EmailVerifiedAt == nil {
		t.Errorf("me = %+v", got)
	}
}

func TestReportsAdminRoleAndUnverifiedEmail(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	if err := f.app.Identity.Users.GrantRole(t.Context(), session.User.ID, domain.RoleAdmin); err != nil {
		t.Fatal(err)
	}

	got := decodeMe(t, f.getMe(session.AccessToken).AssertStatus(http.StatusOK))
	if !got.IsAdmin || got.EmailVerifiedAt != nil {
		t.Errorf("me = %+v, want an unverified admin", got)
	}
}

func TestPatchChangesOnlyTheFieldsSent(t *testing.T) {
	t.Parallel()

	patches := map[string]map[string]any{
		"name":   {"name": "New Name"},
		"email":  {"email": "new@example.com"},
		"locale": {"locale": "ru"},
		"theme":  {"theme_preference": "dark"},
	}

	for name, patch := range patches {
		t.Run(name, func(t *testing.T) {
			t.Parallel()

			f := newFixture(t)
			session := f.verified(t, "ada@example.com")
			before := decodeMe(t, f.getMe(session.AccessToken))

			after := decodeMe(t, f.patchMe(session.AccessToken, patch).AssertStatus(http.StatusOK))

			want := before
			for field, value := range patch {
				switch field {
				case "name":
					want.Name = value.(string)
				case "email":
					want.Email, want.EmailVerifiedAt = value.(string), nil
				case "locale":
					want.Locale = value.(string)
				case "theme_preference":
					want.ThemePreference = value.(string)
				}
			}

			if !sameExceptVerified(after, want) || (after.EmailVerifiedAt == nil) != (want.EmailVerifiedAt == nil) {
				t.Errorf("after = %+v, want %+v", after, want)
			}
		})
	}
}

func sameExceptVerified(a, b me) bool {
	a.EmailVerifiedAt, b.EmailVerifiedAt = nil, nil

	return a == b
}

func TestPatchAppliesSeveralFieldsTogether(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	got := decodeMe(t, f.patchMe(token, map[string]any{"name": "Ada L", "locale": "ru", "theme_preference": "light"}).AssertStatus(http.StatusOK))
	if got.Name != "Ada L" || got.Locale != "ru" || got.ThemePreference != "light" {
		t.Errorf("me = %+v", got)
	}
}

func TestPatchWithAnEmptyBodyIsRejected(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	// The contract's minProperties: 1 rejects it for the whole body; PHP
	// reports it on /name (its required_without_all rule).
	res := f.patchMe(f.register(t, "ada@example.com").AccessToken, map[string]any{}).AssertStatus(http.StatusUnprocessableEntity)
	if got := fieldErrors(t, res); !slices.Equal(got, []string{" required"}) {
		t.Errorf("errors = %v", got)
	}
}

func TestPatchRejectsUnknownKeys(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	res := f.patchMe(f.register(t, "ada@example.com").AccessToken, map[string]any{"name": "Ada", "nickname": "ada"}).AssertStatus(http.StatusUnprocessableEntity)
	if got := fieldErrors(t, res); !slices.Equal(got, []string{"/nickname unknown_field"}) {
		t.Errorf("errors = %v", got)
	}
}

func TestChangingTheEmailUnverifiesTheAccountAndSendsANewLink(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.verified(t, "ada@example.com").AccessToken

	got := decodeMe(t, f.patchMe(token, map[string]any{"email": "new@example.com"}).AssertStatus(http.StatusOK))
	if got.EmailVerifiedAt != nil {
		t.Error("the account is still verified after the email changed")
	}

	f.verify(token, verifyBody(t, f.lastLink(t, "new@example.com"))).AssertStatus(http.StatusNoContent)

	if !f.verifiedAt(t, "new@example.com") {
		t.Error("the new address did not verify")
	}
}

func TestKeepingTheSameEmailKeepsTheAccountVerified(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.verified(t, "ada@example.com").AccessToken

	got := decodeMe(t, f.patchMe(token, map[string]any{"name": "Updated", "email": "ada@example.com"}).AssertStatus(http.StatusOK))
	if got.EmailVerifiedAt == nil || len(f.mail.Sent("ada@example.com")) != 1 {
		t.Errorf("me = %+v; emails = %d; want still verified and no new email", got, len(f.mail.Sent("ada@example.com")))
	}
}

func TestPatchRejectsADuplicateEmail(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.register(t, "taken@example.com")
	token := f.register(t, "ada@example.com").AccessToken

	if got := fieldErrors(t, f.patchMe(token, map[string]any{"email": "taken@example.com"})); !slices.Equal(got, []string{"/email not_unique"}) {
		t.Errorf("errors = %v", got)
	}
}

func TestPatchRejectsANonStringEmail(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	if got := fieldErrors(t, f.patchMe(token, map[string]any{"email": []any{map[string]any{"nested": 1}}})); !slices.Equal(got, []string{"/email invalid_format"}) {
		t.Errorf("errors = %v", got)
	}
}

func TestPatchRejectsAnUnknownLocaleOrTheme(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	for body, want := range map[string]string{
		`{"locale":"fr"}`:               "/locale invalid_value",
		`{"theme_preference":"purple"}`: "/theme_preference invalid_value",
		`{"locale":""}`:                 "/locale required",
		`{"name":""}`:                   "/name required",
	} {
		if got := fieldErrors(t, f.patchMe(token, body)); !slices.Equal(got, []string{want}) {
			t.Errorf("%s: errors = %v, want %s", body, got, want)
		}
	}
}

func (f *fixture) putPassword(token string, body any) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodPut, Path: "/api/v1/me/password", Token: token, Body: body})
}

func TestPasswordCanBeChanged(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	f.putPassword(token, map[string]any{"current_password": password, "password": "new-password-123"}).AssertStatus(http.StatusNoContent)
	f.login("ada@example.com", "new-password-123", "native", nil).AssertStatus(http.StatusOK)
}

func TestPasswordChangeRevokesOtherSessionsButKeepsTheCurrentOne(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	current := f.register(t, "ada@example.com")
	other := decodeAuth(t, f.login("ada@example.com", password, "native", nil))

	f.putPassword(current.AccessToken, map[string]any{"current_password": password, "password": "new-password-123"}).AssertStatus(http.StatusNoContent)

	f.refresh(current.RefreshToken).AssertStatus(http.StatusOK)
	f.refresh(other.RefreshToken).AssertStatus(http.StatusUnauthorized)
}

func TestPasswordChangeValidation(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	for name, tt := range map[string]struct {
		body any
		want []string
	}{
		"wrong current password": {map[string]any{"current_password": "wrong", "password": "new-password-123"}, []string{"/current_password mismatch"}},
		"weak new password":      {map[string]any{"current_password": password, "password": "short"}, []string{"/password too_short"}},
		"confirmation field":     {map[string]any{"current_password": password, "password": "new-password-123", "password_confirmation": "x"}, []string{"/password_confirmation unknown_field"}},
		"non-string current pw":  {map[string]any{"current_password": []any{nil, nil}, "password": "new-password-123"}, []string{"/current_password invalid_format"}},
	} {
		if got := fieldErrors(t, f.putPassword(token, tt.body).AssertStatus(http.StatusUnprocessableEntity)); !slices.Equal(got, tt.want) {
			t.Errorf("%s: errors = %v, want %v", name, got, tt.want)
		}
	}
}

func (f *fixture) deleteMe(token string, body any) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodDelete, Path: "/api/v1/me", Token: token, Body: body})
}

func TestAccountCanBeDeletedAndEverySessionIsRevoked(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	f.deleteMe(session.AccessToken, map[string]any{"password": password}).AssertStatus(http.StatusNoContent)

	if n := f.count(t, "SELECT count(*) FROM users WHERE id = $1", session.User.ID); n != 0 {
		t.Error("the user still exists")
	}

	if n := f.count(t, "SELECT count(*) FROM refresh_tokens WHERE user_id = $1", session.User.ID); n != 0 {
		t.Errorf("refresh tokens = %d, want them deleted with the user", n)
	}

	f.refresh(session.RefreshToken).AssertStatus(http.StatusUnauthorized)
}

func TestAWrongPasswordPreventsDeletion(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	if got := fieldErrors(t, f.deleteMe(session.AccessToken, map[string]any{"password": "wrong"})); !slices.Equal(got, []string{"/password mismatch"}) {
		t.Errorf("errors = %v", got)
	}

	if got := fieldErrors(t, f.deleteMe(session.AccessToken, map[string]any{"password": []any{"x"}})); !slices.Equal(got, []string{"/password invalid_format"}) {
		t.Errorf("non-string password: errors = %v", got)
	}

	if n := f.count(t, "SELECT count(*) FROM users WHERE id = $1", session.User.ID); n != 1 {
		t.Error("the user was deleted")
	}
}

func TestEveryMeRouteRequiresAuthentication(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	for _, res := range []*apitest.Response{
		f.getMe(""),
		f.patchMe("", map[string]any{"name": "X"}),
		f.putPassword("", map[string]any{"current_password": "a", "password": "b"}),
		f.deleteMe("", map[string]any{"password": "a"}),
	} {
		if code := problemCode(t, res.AssertStatus(http.StatusUnauthorized)); code != "unauthenticated" {
			t.Errorf("code = %q", code)
		}
	}
}

func TestTheRetiredProfileRoutesAreGone(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	for _, r := range []struct{ method, path string }{
		{http.MethodPatch, "/api/v1/profile"},
		{http.MethodDelete, "/api/v1/profile"},
		{http.MethodPut, "/api/v1/profile/password"},
		{http.MethodPatch, "/api/v1/profile/locale"},
		{http.MethodPatch, "/api/v1/profile/theme"},
	} {
		if code := problemCode(t, f.api.Do(apitest.Request{Method: r.method, Path: r.path, Token: token, Body: map[string]any{}}).AssertStatus(http.StatusNotFound)); code != "not_found" {
			t.Errorf("%s %s: code = %q", r.method, r.path, code)
		}
	}
}

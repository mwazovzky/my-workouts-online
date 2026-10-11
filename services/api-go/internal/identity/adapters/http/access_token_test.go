package http_test

import (
	"encoding/base64"
	"encoding/json"
	"net/http"
	"strings"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/AccessTokenTest.php. GET /api/v1/programs
// is a protected operation no story implements yet: a token that passes
// authentication gets the 501 stub, one that fails gets the 401.

func (f *fixture) programs(token string) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/programs", Token: token, SkipContract: true})
}

func TestValidTokenIsAccepted(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	f.programs(f.register(t, "ada@example.com").AccessToken).AssertStatus(http.StatusNotImplemented)
}

func TestExpiredTokenReturnsTokenExpiredCode(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	f.clock.Advance(16 * time.Minute)

	res := f.programs(token).AssertStatus(http.StatusUnauthorized)
	if code := problemCode(t, res); code != "token_expired" {
		t.Errorf("code = %q, want token_expired", code)
	}
}

func TestTokenWithinLeewayIsStillAccepted(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken

	f.clock.Advance(15*time.Minute + 10*time.Second)
	f.programs(token).AssertStatus(http.StatusNotImplemented)
}

func TestTamperedTokenIsRejected(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").AccessToken
	parts := strings.Split(token, ".")

	claims := claimsOf(t, token)
	claims["sub"] = "999"
	payload, _ := json.Marshal(claims)

	forged := parts[0] + "." + base64.RawURLEncoding.EncodeToString(payload) + "." + parts[2]

	if code := problemCode(t, f.programs(forged).AssertStatus(http.StatusUnauthorized)); code != "unauthenticated" {
		t.Errorf("code = %q, want unauthenticated", code)
	}
}

func TestGarbageTokenIsRejected(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	if code := problemCode(t, f.programs("not-a-jwt").AssertStatus(http.StatusUnauthorized)); code != "unauthenticated" {
		t.Errorf("code = %q, want unauthenticated", code)
	}
}

func TestTokenForDeletedUserIsRejected(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	if _, err := f.pool.Exec(t.Context(), "DELETE FROM users WHERE id = $1", session.User.ID); err != nil {
		t.Fatal(err)
	}

	f.programs(session.AccessToken).AssertStatus(http.StatusUnauthorized)
}

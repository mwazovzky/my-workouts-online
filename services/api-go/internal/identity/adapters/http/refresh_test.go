package http_test

import (
	"net/http"
	"slices"
	"sync"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors tests/Feature/Api/Auth/RefreshTest.php.

func TestNativeClientRotatesRefreshToken(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	rotated := decodeAuth(t, f.refresh(session.RefreshToken).AssertStatus(http.StatusOK))
	if rotated.RefreshToken == "" || rotated.RefreshToken == session.RefreshToken || rotated.User.ID != session.User.ID {
		t.Errorf("refresh = %+v, want a new refresh token for the same user", rotated)
	}

	if n := f.count(t, "SELECT count(*) FROM refresh_tokens"); n != 2 {
		t.Errorf("tokens = %d, want 2", n)
	}

	same := f.count(t, `SELECT count(*) FROM refresh_tokens old JOIN refresh_tokens r ON r.id = old.replaced_by_id
		WHERE old.token_hash = $1 AND r.family_id = old.family_id AND old.revoked_at IS NOT NULL`, domain.HashToken(session.RefreshToken))
	if same != 1 {
		t.Error("the old token is not rotated into a token of its family")
	}
}

func TestWebClientRotatesViaCookieWithCSRFHeader(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	res := f.api.Do(apitest.Request{
		Method: http.MethodPost, Path: "/api/v1/auth/refresh",
		Header: http.Header{"X-Requested-With": {"XMLHttpRequest"}, "Cookie": {"refresh_token=" + session.RefreshToken}},
	}).AssertStatus(http.StatusOK)

	if decodeAuth(t, res).RefreshToken != "" {
		t.Error("a web refresh put the refresh token in the body")
	}

	if cookie := refreshCookie(t, res); cookie == nil || cookie.Value == session.RefreshToken {
		t.Errorf("cookie = %+v, want a new refresh token", cookie)
	}
}

// The contract makes X-Requested-With a required header, so its absence is
// a 422 from request validation, where PHP answers 403 from the handler.
func TestWebClientWithoutCSRFHeaderIsRefused(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	res := f.api.Do(apitest.Request{
		Method: http.MethodPost, Path: "/api/v1/auth/refresh",
		Header: http.Header{"Cookie": {"refresh_token=" + session.RefreshToken}},
	}).AssertStatus(http.StatusUnprocessableEntity)

	if got := fieldErrors(t, res); !slices.Equal(got, []string{"/X-Requested-With required"}) {
		t.Errorf("errors = %v", got)
	}
}

func TestReusingARotatedTokenRevokesTheWholeFamily(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")
	next := decodeAuth(t, f.refresh(session.RefreshToken).AssertStatus(http.StatusOK)).RefreshToken

	if code := problemCode(t, f.refresh(session.RefreshToken).AssertStatus(http.StatusUnauthorized)); code != "refresh_token_reused" {
		t.Errorf("reuse: code = %q", code)
	}

	if code := problemCode(t, f.refresh(next).AssertStatus(http.StatusUnauthorized)); code != "refresh_token_invalid" {
		t.Errorf("the family's newest token: code = %q", code)
	}

	if n := f.count(t, "SELECT count(*) FROM refresh_tokens WHERE revoked_at IS NULL"); n != 0 {
		t.Errorf("live tokens = %d, want 0", n)
	}
}

func TestExpiredRefreshTokenIsRejected(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	f.clock.Advance(31 * 24 * time.Hour)

	if code := problemCode(t, f.refresh(session.RefreshToken).AssertStatus(http.StatusUnauthorized)); code != "refresh_token_expired" {
		t.Errorf("code = %q", code)
	}
}

func TestUnknownAndMissingTokensAreRejected(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	if code := problemCode(t, f.refresh("nope").AssertStatus(http.StatusUnauthorized)); code != "refresh_token_invalid" {
		t.Errorf("unknown: code = %q", code)
	}

	missing := f.api.Do(apitest.Request{
		Method: http.MethodPost, Path: "/api/v1/auth/refresh", Header: http.Header{"X-Requested-With": {"XMLHttpRequest"}},
	}).AssertStatus(http.StatusUnauthorized)

	if code := problemCode(t, missing); code != "refresh_token_missing" {
		t.Errorf("missing: code = %q", code)
	}
}

func TestRefreshPreservesTheOriginalAuthenticationTime(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	session := f.register(t, "ada@example.com")

	f.clock.Advance(10 * time.Minute)

	refreshed := decodeAuth(t, f.refresh(session.RefreshToken).AssertStatus(http.StatusOK))
	if authTime(t, refreshed.AccessToken) != authTime(t, session.AccessToken) {
		t.Error("refresh changed auth_time")
	}
}

// Two refreshes of one token at once: the row lock lets exactly one rotate
// it; the other is reuse and revokes the family.
func TestConcurrentRefreshesOfOneTokenSucceedOnce(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	token := f.register(t, "ada@example.com").RefreshToken

	const attempts = 4

	var (
		wg       sync.WaitGroup
		mu       sync.Mutex
		statuses []int
	)

	for range attempts {
		wg.Go(func() {
			res := f.refresh(token)

			mu.Lock()
			statuses = append(statuses, res.Code)
			mu.Unlock()
		})
	}

	wg.Wait()

	if ok := slices.Index(statuses, http.StatusOK); ok < 0 || slices.Index(statuses[ok+1:], http.StatusOK) >= 0 {
		t.Errorf("statuses = %v, want exactly one 200", statuses)
	}
}

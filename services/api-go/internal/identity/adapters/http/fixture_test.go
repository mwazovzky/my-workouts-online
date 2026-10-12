package http_test

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"log/slog"
	"net/http"
	"net/url"
	"regexp"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

// The key pair, issuer and audience of services/api-php/phpunit.xml.
var testJWT = config.JWT{
	Issuer:            "http://localhost",
	Audience:          "my-workouts-test",
	PrivateKey:        "SNdNP8JHR4IZdp8IesFeqOLNQr2l8U05g/h7h4l2ho8nsYzYswZYM40Mn8Ez9bAYRKT96SVgnnDdHOK7M+khjg==",
	PublicKey:         "J7GM2LMGWDONDJ/BM/WwGESk/eklYJ5w3RziuzPpIY4=",
	AccessTTLSeconds:  900,
	RefreshTTLSeconds: 2592000,
	LeewaySeconds:     30,
}

// fixture is the application on its own database, at a settable time.
type fixture struct {
	api   *apitest.Client
	app   *bootstrap.App
	pool  *pgxpool.Pool
	clock *clock.Fake
	mail  *mail.Fake
}

func newFixture(t *testing.T) *fixture {
	t.Helper()

	f := &fixture{pool: testdb.New(t), clock: clock.NewFake(time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC)), mail: &mail.Fake{}}

	app, err := bootstrap.New(bootstrap.Deps{
		Config: config.Config{
			AppURL: "http://localhost:8080", RateLimitPerMinute: 100_000, AppEnv: "testing", BcryptRounds: 4, JWT: testJWT,
			AppName: "My Workouts", FrontendURL: "https://app.example.test", LinkSigningKey: "test-link-signing-key",
		},
		Logger: slog.New(slog.DiscardHandler),
		Clock:  f.clock,
		Pool:   f.pool,
		Mailer: f.mail,
	})
	if err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { _ = app.Close(context.Background()) })

	f.app, f.api = app, apitest.New(t, app.Handler)

	return f
}

const password = "secret123"

type authResponse struct {
	AccessToken  string `json:"access_token"`
	RefreshToken string `json:"refresh_token"`
	TokenType    string `json:"token_type"`
	ExpiresIn    int    `json:"expires_in"`
	User         struct {
		ID              int64   `json:"id"`
		Email           string  `json:"email"`
		Locale          string  `json:"locale"`
		ThemePreference string  `json:"theme_preference"`
		IsAdmin         bool    `json:"is_admin"`
		EmailVerifiedAt *string `json:"email_verified_at"`
	} `json:"user"`
}

func decodeAuth(t *testing.T, res *apitest.Response) authResponse {
	t.Helper()

	var body authResponse
	if err := json.Unmarshal(res.Body.Bytes(), &body); err != nil {
		t.Fatalf("not an AuthResponse: %v\n%s", err, res.Body)
	}

	return body
}

// register signs up email with password as a native client.
func (f *fixture) register(t *testing.T, email string) authResponse {
	t.Helper()

	res := f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/register", Body: map[string]any{
		"name": "Ada", "email": email, "password": password, "client": "native",
	}}).AssertStatus(http.StatusCreated)

	return decodeAuth(t, res)
}

func (f *fixture) refresh(token string) *apitest.Response {
	return f.api.Do(apitest.Request{
		Method: http.MethodPost, Path: "/api/v1/auth/refresh",
		Header: http.Header{"X-Requested-With": {"XMLHttpRequest"}},
		Body:   map[string]any{"refresh_token": token},
	})
}

// count runs a count(*) query.
func (f *fixture) count(t *testing.T, query string, args ...any) int {
	t.Helper()

	var n int
	if err := f.pool.QueryRow(t.Context(), query, args...).Scan(&n); err != nil {
		t.Fatal(err)
	}

	return n
}

func refreshCookie(t *testing.T, res *apitest.Response) *http.Cookie {
	t.Helper()

	for _, c := range (&http.Response{Header: res.Header()}).Cookies() {
		if c.Name == "refresh_token" {
			return c
		}
	}

	return nil
}

// claimsOf decodes an access token's payload without verifying it.
func claimsOf(t *testing.T, token string) map[string]any {
	t.Helper()

	parts := strings.Split(token, ".")
	if len(parts) != 3 {
		t.Fatalf("not a JWT: %q", token)
	}

	data, err := base64.RawURLEncoding.DecodeString(parts[1])
	if err != nil {
		t.Fatal(err)
	}

	var claims map[string]any
	if err := json.Unmarshal(data, &claims); err != nil {
		t.Fatal(err)
	}

	return claims
}

func authTime(t *testing.T, token string) int64 {
	t.Helper()

	return int64(claimsOf(t, token)["auth_time"].(float64))
}

func problemCode(t *testing.T, res *apitest.Response) string {
	t.Helper()

	return string(res.Problem().Code)
}

// fieldErrors lists "pointer code" for each validation error.
func fieldErrors(t *testing.T, res *apitest.Response) []string {
	t.Helper()

	var out []string
	for _, e := range res.Problem().Errors {
		out = append(out, e.Pointer+" "+string(e.Code))
	}

	return out
}

// lastLink returns the link in the newest email to address.
func (f *fixture) lastLink(t *testing.T, address string) *url.URL {
	t.Helper()

	sent := f.mail.Sent(address)
	if len(sent) == 0 {
		t.Fatalf("no email to %s", address)
	}

	raw := regexp.MustCompile(`https://\S+`).FindString(sent[len(sent)-1].Text)

	u, err := url.Parse(raw)
	if err != nil || raw == "" {
		t.Fatalf("no link in %q", sent[len(sent)-1].Text)
	}

	return u
}

// verifyBody is what the SPA's VerifyEmailLink page posts for a link.
func verifyBody(t *testing.T, link *url.URL) map[string]any {
	t.Helper()

	segments := strings.Split(strings.TrimPrefix(link.Path, "/"), "/")
	if len(segments) != 3 || segments[0] != "verify-email" {
		t.Fatalf("not a verification link: %s", link)
	}

	id, _ := strconv.Atoi(segments[1])
	expires, _ := strconv.Atoi(link.Query().Get("expires"))

	return map[string]any{"id": id, "hash": segments[2], "expires": expires, "signature": link.Query().Get("signature")}
}

// verified registers email and verifies it through the emailed link.
func (f *fixture) verified(t *testing.T, email string) authResponse {
	t.Helper()

	session := f.register(t, email)
	f.api.Do(apitest.Request{
		Method: http.MethodPost, Path: "/api/v1/auth/email/verify", Token: session.AccessToken,
		Body: verifyBody(t, f.lastLink(t, email)),
	}).AssertStatus(http.StatusNoContent)

	return session
}

package http_test

import (
	"context"
	_ "embed"
	"encoding/json"
	"log/slog"
	"net/http"
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

// catalogSQL is the catalog the golden files were captured from
// (testdata/README.md).
//
//go:embed testdata/catalog.sql
var catalogSQL string

// The key pair of services/api-php/phpunit.xml.
var testJWT = config.JWT{
	Issuer:            "http://localhost",
	Audience:          "my-workouts-test",
	PrivateKey:        "SNdNP8JHR4IZdp8IesFeqOLNQr2l8U05g/h7h4l2ho8nsYzYswZYM40Mn8Ez9bAYRKT96SVgnnDdHOK7M+khjg==",
	PublicKey:         "J7GM2LMGWDONDJ/BM/WwGESk/eklYJ5w3RziuzPpIY4=",
	AccessTTLSeconds:  900,
	RefreshTTLSeconds: 2592000,
	LeewaySeconds:     30,
}

// fixture is the application on its own database holding the golden catalog.
type fixture struct {
	api  *apitest.Client
	pool *pgxpool.Pool
}

func newFixture(t *testing.T) *fixture {
	t.Helper()

	f := &fixture{pool: testdb.New(t)}

	app, err := bootstrap.New(bootstrap.Deps{
		Config: config.Config{
			AppURL: "http://localhost:8080", RateLimitPerMinute: 100_000, AppEnv: "testing", BcryptRounds: 4, JWT: testJWT,
			AppName: "My Workouts", FrontendURL: "https://app.example.test", LinkSigningKey: "test-link-signing-key",
		},
		Logger: slog.New(slog.DiscardHandler),
		Clock:  clock.NewFake(time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC)),
		Pool:   f.pool,
		Mailer: &mail.Fake{},
	})
	if err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { _ = app.Close(context.Background()) })

	f.api = apitest.New(t, app.Handler)
	f.exec(t, catalogSQL)

	return f
}

// user is a signed-up account.
type user struct {
	ID    int64
	Token string
}

// signUp registers email and verifies it unless unverified is set. The
// first user of a fixture has id 1, as in the PHP capture.
func (f *fixture) signUp(t *testing.T, email string, unverified ...bool) user {
	t.Helper()

	res := f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/register", Body: map[string]any{
		"name": "Ada", "email": email, "password": "secret123", "client": "native",
	}}).AssertStatus(http.StatusCreated)

	var body struct {
		AccessToken string `json:"access_token"`
		User        struct {
			ID int64 `json:"id"`
		} `json:"user"`
	}

	decode(t, res, &body)

	if len(unverified) == 0 || !unverified[0] {
		f.exec(t, "UPDATE users SET email_verified_at = now() WHERE id = $1", body.User.ID)
	}

	return user{ID: body.User.ID, Token: body.AccessToken}
}

func (f *fixture) exec(t *testing.T, sql string, args ...any) {
	t.Helper()

	if _, err := f.pool.Exec(t.Context(), sql, args...); err != nil {
		t.Fatal(err)
	}
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

func (f *fixture) get(u user, path, locale string) *apitest.Response {
	return f.api.Do(apitest.Request{Method: http.MethodGet, Path: path, Token: u.Token, Locale: locale})
}

func (f *fixture) enrollment(u user, method, programID string) *apitest.Response {
	return f.api.Do(apitest.Request{Method: method, Path: "/api/v1/programs/" + programID + "/enrollment", Token: u.Token})
}

func decode(t *testing.T, res *apitest.Response, v any) {
	t.Helper()

	if err := json.Unmarshal(res.Body.Bytes(), v); err != nil {
		t.Fatalf("decode %s: %v", res.Body, err)
	}
}

// names returns the name of every item in a {"data": [...]} body.
func names(t *testing.T, res *apitest.Response) []string {
	t.Helper()

	var body struct {
		Data []struct {
			Name string `json:"name"`
		} `json:"data"`
	}

	decode(t, res, &body)

	out := make([]string, len(body.Data))
	for i, item := range body.Data {
		out[i] = item.Name
	}

	return out
}

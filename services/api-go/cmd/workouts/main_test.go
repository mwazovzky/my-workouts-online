package main

import (
	"bufio"
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"regexp"
	"strings"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/jwt"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

func TestRunRejectsMissingAndUnknownCommands(t *testing.T) {
	t.Parallel()

	for _, args := range [][]string{
		nil,
		{"launch"},
		{"serve", "-unknown-flag"},
		{"migrate"},
		{"migrate", "down"},
		{"user"},
		{"user", "grant-admin"},
		{"user", "revoke-admin", "a@b.c"},
		{"keys"},
		{"keys", "rotate"},
	} {
		var stderr bytes.Buffer

		err := run(t.Context(), args, nil, io.Discard, &stderr)
		if !errors.Is(err, errUsage) {
			t.Errorf("run(%q) error = %v, want errUsage", args, err)
		}

		if !strings.Contains(stderr.String(), "usage: workouts") {
			t.Errorf("run(%q) printed %q, want the usage text", args, stderr.String())
		}
	}
}

func TestServeFailsWithoutRequiredConfig(t *testing.T) {
	t.Parallel()

	for _, command := range [][]string{{"serve"}, {"migrate", "up"}} {
		err := run(t.Context(), command, map[string]string{}, io.Discard, io.Discard)
		if err == nil || !strings.Contains(err.Error(), "DATABASE_URL") {
			t.Errorf("run(%q) error = %v, want it to name DATABASE_URL", command, err)
		}
	}
}

func TestServeFailsWhenTheDatabaseIsUnreachable(t *testing.T) {
	t.Parallel()

	err := run(t.Context(), []string{"serve"}, map[string]string{
		"DATABASE_URL": "postgres://nobody@127.0.0.1:1/none?connect_timeout=1",
		"APP_ENV":      "testing",
	}, io.Discard, io.Discard)
	if err == nil || !strings.Contains(err.Error(), "ping") {
		t.Errorf("run(serve) error = %v, want a ping error", err)
	}
}

func TestMigrateUpAppliesEveryMigrationOnce(t *testing.T) {
	t.Parallel()

	environ := map[string]string{"DATABASE_URL": testdb.NewEmpty(t).Config().ConnString(), "APP_ENV": "testing"}

	var first, second, status bytes.Buffer
	if err := run(t.Context(), []string{"migrate", "up"}, environ, &first, io.Discard); err != nil {
		t.Fatalf("migrate up: %v", err)
	}

	if err := run(t.Context(), []string{"migrate", "up"}, environ, &second, io.Discard); err != nil {
		t.Fatalf("second migrate up: %v", err)
	}

	if err := run(t.Context(), []string{"migrate", "status"}, environ, &status, io.Discard); err != nil {
		t.Fatalf("migrate status: %v", err)
	}

	migrations := []string{"00001_identity.sql", "00002_catalog.sql", "00003_training.sql", "00004 (go)"}
	for _, name := range migrations {
		if !strings.Contains(first.String(), "applied "+name) {
			t.Errorf("first up output %q does not apply %s", first.String(), name)
		}

		if !regexp.MustCompile(regexp.QuoteMeta(name) + `\s+applied\s+\d{4}-`).MatchString(status.String()) {
			t.Errorf("status output %q does not list %s as applied", status.String(), name)
		}
	}

	if got := second.String(); got != "no pending migrations\n" {
		t.Errorf("second up output = %q, want no pending migrations", got)
	}
}

func TestServeAnswersHealthAndStopsCleanly(t *testing.T) {
	t.Parallel()

	databaseURL := testdb.New(t).Config().ConnString()
	logs, addrs := listeningAddr(t)
	ctx, cancel := context.WithCancel(t.Context())
	defer cancel()

	done := make(chan error, 1)
	go func() {
		done <- run(ctx, []string{"serve"}, map[string]string{
			"DATABASE_URL":         databaseURL,
			"APP_ENV":              "testing",
			"HTTP_ADDR":            "127.0.0.1:0",
			"SHUTDOWN_DRAIN_DELAY": "0s",
			"JWT_PRIVATE_KEY":      testPrivateKey,
			"JWT_PUBLIC_KEY":       testPublicKey,
		}, io.Discard, logs)
	}()

	var addr string
	select {
	case addr = <-addrs:
	case err := <-done:
		t.Fatalf("run(serve) returned early: %v", err)
	}

	for _, path := range []string{"/health", "/health/ready"} {
		if status := statusOf(t, "http://"+addr+path); status != http.StatusOK {
			t.Errorf("GET %s = %d, want %d", path, status, http.StatusOK)
		}
	}

	cancel()

	if err := <-done; err != nil {
		t.Errorf("run(serve) error = %v, want nil", err)
	}
}

func TestHealthcheck(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		status  int
		wantErr bool
	}{
		{"healthy", http.StatusOK, false},
		{"unhealthy", http.StatusServiceUnavailable, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				if r.URL.Path != "/health" {
					w.WriteHeader(http.StatusNotFound)

					return
				}

				w.WriteHeader(tt.status)
			}))
			t.Cleanup(srv.Close)

			err := run(t.Context(), []string{"healthcheck"}, environFor(t, srv.Listener.Addr()), io.Discard, io.Discard)
			if (err != nil) != tt.wantErr {
				t.Errorf("run(healthcheck) error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func TestHealthcheckFailsWhenNothingListens(t *testing.T) {
	t.Parallel()

	ln, err := (&net.ListenConfig{}).Listen(t.Context(), "tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}

	addr := ln.Addr()
	if err := ln.Close(); err != nil {
		t.Fatal(err)
	}

	if err := run(t.Context(), []string{"healthcheck"}, environFor(t, addr), io.Discard, io.Discard); err == nil {
		t.Error("run(healthcheck) error = nil, want a connection error")
	}
}

func environFor(t *testing.T, addr net.Addr) map[string]string {
	t.Helper()

	_, port, err := net.SplitHostPort(addr.String())
	if err != nil {
		t.Fatal(err)
	}

	return map[string]string{"DATABASE_URL": "postgres://unused", "HTTP_ADDR": ":" + port, "APP_ENV": "testing"}
}

// listeningAddr returns a log writer and a channel that receives the address
// from the server's "listening" log line.
func listeningAddr(t *testing.T) (io.Writer, <-chan string) {
	t.Helper()

	reader, writer := io.Pipe()
	t.Cleanup(func() { _ = reader.Close() })

	addrs := make(chan string, 1)
	go func() {
		scanner := bufio.NewScanner(reader)
		for scanner.Scan() {
			var line struct {
				Msg  string `json:"msg"`
				Addr string `json:"addr"`
			}
			if json.Unmarshal(scanner.Bytes(), &line) == nil && line.Msg == "listening" {
				addrs <- line.Addr
			}
		}
	}()

	return writer, addrs
}

func statusOf(t *testing.T, url string) int {
	t.Helper()

	req, err := http.NewRequestWithContext(t.Context(), http.MethodGet, url, http.NoBody)
	if err != nil {
		t.Fatal(err)
	}

	// Without keep-alives: a pooled client may dial a spare connection it
	// never uses, and Shutdown waits up to 5s for such a new connection.
	client := &http.Client{Transport: &http.Transport{DisableKeepAlives: true}}

	resp, err := client.Do(req)
	if err != nil {
		t.Fatal(err)
	}

	if err := resp.Body.Close(); err != nil {
		t.Fatal(err)
	}

	return resp.StatusCode
}

// The phpunit.xml key pair.
const (
	testPrivateKey = "SNdNP8JHR4IZdp8IesFeqOLNQr2l8U05g/h7h4l2ho8nsYzYswZYM40Mn8Ez9bAYRKT96SVgnnDdHOK7M+khjg=="
	testPublicKey  = "J7GM2LMGWDONDJ/BM/WwGESk/eklYJ5w3RziuzPpIY4="
)

func TestSeedRefusesInProduction(t *testing.T) {
	t.Parallel()

	// Production requires a real link signing key before anything runs.
	environ := map[string]string{"DATABASE_URL": testdb.New(t).Config().ConnString(), "LINK_SIGNING_KEY": strings.Repeat("k", 32)}

	for _, env := range []string{"", "production"} {
		if env != "" {
			environ["APP_ENV"] = env
		}

		if err := run(t.Context(), []string{"seed"}, environ, io.Discard, io.Discard); !errors.Is(err, errProduction) {
			t.Errorf("APP_ENV=%q: seed error = %v, want errProduction", env, err)
		}
	}
}

func TestSeedAndGrantAdmin(t *testing.T) {
	t.Parallel()

	pool := testdb.New(t)
	environ := map[string]string{"DATABASE_URL": pool.Config().ConnString(), "APP_ENV": "testing", "BCRYPT_ROUNDS": "4"}

	var out bytes.Buffer

	for range 2 { // seeding twice resets the same account
		if err := run(t.Context(), []string{"seed"}, environ, &out, io.Discard); err != nil {
			t.Fatalf("seed: %v", err)
		}
	}

	if !strings.Contains(out.String(), "seeded the demo admin test@example.com") {
		t.Errorf("seed output = %q", out.String())
	}

	var admins int
	if err := pool.QueryRow(t.Context(), `SELECT count(*) FROM users JOIN role_user ON role_user.user_id = users.id
		WHERE email = 'test@example.com' AND email_verified_at IS NOT NULL`).Scan(&admins); err != nil || admins != 1 {
		t.Errorf("verified admins = %d, %v; want 1", admins, err)
	}

	if _, err := pool.Exec(t.Context(), `INSERT INTO users (name, email, password_hash, email_verified_at) VALUES
		('Bea', 'bea@example.com', 'x', now()), ('Cy', 'cy@example.com', 'x', NULL)`); err != nil {
		t.Fatal(err)
	}

	tests := []struct {
		email   string
		wantOut string
		wantErr string
	}{
		{"bea@example.com", "bea@example.com is now an admin.", ""},
		{"bea@example.com", "bea@example.com is already an admin.", ""},
		{"cy@example.com", "", "not verified"},
		{"nobody@example.com", "", "no user is registered"},
	}

	for _, tt := range tests {
		var stdout bytes.Buffer

		err := run(t.Context(), []string{"user", "grant-admin", tt.email}, environ, &stdout, io.Discard)

		if tt.wantErr == "" && (err != nil || strings.TrimSpace(stdout.String()) != tt.wantOut) {
			t.Errorf("grant-admin %s = %q, %v; want %q", tt.email, stdout.String(), err, tt.wantOut)
		}

		if tt.wantErr != "" && (err == nil || !strings.Contains(err.Error(), tt.wantErr)) {
			t.Errorf("grant-admin %s error = %v, want %q", tt.email, err, tt.wantErr)
		}
	}
}

func TestKeysGenerate(t *testing.T) {
	t.Parallel()

	var out bytes.Buffer
	if err := run(t.Context(), []string{"keys", "generate"}, nil, &out, io.Discard); err != nil {
		t.Fatal(err)
	}

	match := regexp.MustCompile(`JWT_PRIVATE_KEY=(\S+)\nJWT_PUBLIC_KEY=(\S+)`).FindStringSubmatch(out.String())
	if match == nil {
		t.Fatalf("output = %q", out.String())
	}

	if _, err := jwt.ParseKeys(match[1], match[2], ""); err != nil {
		t.Errorf("the printed keys do not parse: %v", err)
	}
}

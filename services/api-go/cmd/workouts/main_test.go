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
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"

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

func TestWorkRunsUntilCancelledAndStopsCleanly(t *testing.T) {
	t.Parallel()

	databaseURL := testdb.New(t).Config().ConnString()
	logs, started := logLine(t, "working jobs")
	ctx, cancel := context.WithCancel(t.Context())
	defer cancel()

	done := make(chan error, 1)
	go func() {
		done <- run(ctx, []string{"work"}, map[string]string{
			"DATABASE_URL":     databaseURL,
			"APP_ENV":          "testing",
			"SHUTDOWN_TIMEOUT": "2s",
		}, io.Discard, logs)
	}()

	select {
	case <-started:
	case err := <-done:
		t.Fatalf("run(work) returned early: %v", err)
	}

	cancel()

	select {
	case err := <-done:
		if err != nil {
			t.Errorf("run(work) error = %v, want nil", err)
		}
	case <-time.After(10 * time.Second):
		t.Fatal("run(work) did not stop")
	}
}

func TestWorkFailsWithoutRequiredConfig(t *testing.T) {
	t.Parallel()

	if err := run(t.Context(), []string{"work"}, map[string]string{}, io.Discard, io.Discard); err == nil {
		t.Error("run(work) without DATABASE_URL = nil, want an error")
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

// logLine returns a log writer and a channel closed when a line with msg is
// logged.
func logLine(t *testing.T, msg string) (io.Writer, <-chan struct{}) {
	t.Helper()

	reader, writer := io.Pipe()
	t.Cleanup(func() { _ = reader.Close() })

	seen := make(chan struct{})
	go func() {
		scanner := bufio.NewScanner(reader)
		for scanner.Scan() {
			var line struct {
				Msg string `json:"msg"`
			}
			if json.Unmarshal(scanner.Bytes(), &line) == nil && line.Msg == msg {
				close(seen)

				break
			}
		}

		_, _ = io.Copy(io.Discard, reader)
	}()

	return writer, seen
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
	environ := map[string]string{
		"DATABASE_URL": pool.Config().ConnString(), "APP_ENV": "testing", "BCRYPT_ROUNDS": "4", "CATALOG_DIR": shippedCatalog,
	}

	var out bytes.Buffer

	for range 2 { // seeding twice resets the same account
		if err := run(t.Context(), []string{"seed"}, environ, &out, io.Discard); err != nil {
			t.Fatalf("seed: %v", err)
		}
	}

	if !strings.Contains(out.String(), "seeded the demo admin test@example.com") || !strings.Contains(out.String(), "Catalog synced.") {
		t.Errorf("seed output = %q", out.String())
	}

	var programs int
	if err := pool.QueryRow(t.Context(), "SELECT count(*) FROM programs WHERE slug IS NOT NULL").Scan(&programs); err != nil || programs == 0 {
		t.Errorf("seeded programs = %d, %v; want the catalog's", programs, err)
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

// shippedCatalog is the repository's catalog/ directory, from this package.
const shippedCatalog = "../../../../catalog"

// catalogSync runs `catalog sync` with args on its own database.
func catalogSync(t *testing.T, environ map[string]string, args ...string) (string, error) {
	t.Helper()

	var out bytes.Buffer
	err := run(t.Context(), append([]string{"catalog", "sync"}, args...), environ, &out, io.Discard)

	return out.String(), err
}

func catalogEnviron(t *testing.T) (map[string]string, *pgxpool.Pool) {
	t.Helper()

	pool := testdb.New(t)

	return map[string]string{"DATABASE_URL": pool.Config().ConnString(), "APP_ENV": "testing", "CATALOG_DIR": shippedCatalog}, pool
}

func countRows(t *testing.T, pool *pgxpool.Pool, query string) int {
	t.Helper()

	var n int
	if err := pool.QueryRow(t.Context(), query).Scan(&n); err != nil {
		t.Fatal(err)
	}

	return n
}

// Mirrors CatalogSyncCommandTest::it_syncs_the_shipped_catalog and
// ::it_can_run_repeatedly.
func TestCatalogSyncAppliesTheShippedCatalogRepeatedly(t *testing.T) {
	t.Parallel()

	environ, pool := catalogEnviron(t)

	for range 2 {
		out, err := catalogSync(t, environ)
		if err != nil || !strings.Contains(out, "Catalog synced.") {
			t.Fatalf("catalog sync = %q, %v", out, err)
		}
	}

	if n := countRows(t, pool, "SELECT count(*) FROM exercises WHERE slug IS NULL"); n != 0 {
		t.Errorf("%d exercises without a slug", n)
	}

	if n := countRows(t, pool, "SELECT count(*) FROM programs"); n != 14 {
		t.Errorf("programs = %d, want the 14 in catalog/programs.yaml", n)
	}
}

// Mirrors CatalogSyncCommandTest::it_adopts_the_cycling_program_renamed_with_a_hyphen_in_production.
func TestCatalogSyncAdoptsTheCyclingProgramRenamedWithAHyphen(t *testing.T) {
	t.Parallel()

	environ, pool := catalogEnviron(t)

	var id int64
	if err := pool.QueryRow(t.Context(),
		"INSERT INTO programs (name, description) VALUES ('Cycling - Beginner (Week 1)', 'Edited in the admin UI.') RETURNING id").Scan(&id); err != nil {
		t.Fatal(err)
	}

	out, err := catalogSync(t, environ)
	if err != nil || !strings.Contains(out, "programs [cycling-beginner-week-1]: adopted") {
		t.Fatalf("catalog sync = %q, %v", out, err)
	}

	var slug, name string
	if err := pool.QueryRow(t.Context(), "SELECT slug, name FROM programs WHERE id = $1", id).Scan(&slug, &name); err != nil ||
		slug != "cycling-beginner-week-1" || name != "Cycling — Beginner (Week 1)" {
		t.Errorf("program = %q, %q, %v", slug, name, err)
	}

	if n := countRows(t, pool, "SELECT count(*) FROM program_workout_template WHERE program_id = "+strconv.FormatInt(id, 10)); n != 6 {
		t.Errorf("assignments = %d, want 6", n)
	}
}

// Mirrors CatalogSyncCommandTest::a_dry_run_writes_nothing.
func TestCatalogSyncDryRunWritesNothing(t *testing.T) {
	t.Parallel()

	environ, pool := catalogEnviron(t)

	if out, err := catalogSync(t, environ, "--dry-run"); err != nil || !strings.Contains(out, "Dry run: nothing was written.") {
		t.Fatalf("catalog sync --dry-run = %q, %v", out, err)
	}

	if n := countRows(t, pool, "SELECT count(*) FROM equipment"); n != 0 {
		t.Errorf("equipment = %d after a dry run", n)
	}
}

// Mirrors CatalogSyncCommandTest::it_fails_and_lists_the_violations_when_the_catalog_is_invalid.
func TestCatalogSyncFailsAndListsTheViolations(t *testing.T) {
	t.Parallel()

	environ, pool := catalogEnviron(t)

	for dir, message := range map[string]string{
		shippedCatalog + "/missing":                                    "catalog directory does not exist",
		shippedCatalog + "/testdata/invalid/schedule-template-unknown": "catalog file equipment.yaml does not exist",
	} {
		environ["CATALOG_DIR"] = dir

		out, err := catalogSync(t, environ)
		if !errors.Is(err, errCatalog) || !strings.Contains(out, "The catalog data is invalid. Nothing was written.") || !strings.Contains(out, message) {
			t.Errorf("%s: %q, %v", dir, out, err)
		}
	}

	if n := countRows(t, pool, "SELECT count(*) FROM equipment"); n != 0 {
		t.Errorf("equipment = %d", n)
	}
}

// Mirrors CatalogSyncCommandTest::strict_mode_fails_when_the_sync_raises_warnings
// and ::without_strict_mode_warnings_do_not_fail_the_sync.
func TestCatalogSyncStrictModeFailsOnWarnings(t *testing.T) {
	t.Parallel()

	environ, pool := catalogEnviron(t)

	if _, err := pool.Exec(t.Context(), "INSERT INTO equipment (name) VALUES ('Barbell'), ('Barbell')"); err != nil {
		t.Fatal(err)
	}

	out, err := catalogSync(t, environ, "--strict")
	if !errors.Is(err, errCatalog) || !strings.Contains(out, "Strict mode: the sync raised warnings. Nothing was written.") {
		t.Fatalf("catalog sync --strict = %q, %v", out, err)
	}

	if n := countRows(t, pool, "SELECT count(*) FROM equipment WHERE slug IS NULL"); n != 2 {
		t.Errorf("unmanaged equipment = %d, want 2", n)
	}

	out, err = catalogSync(t, environ)
	if err != nil || !strings.Contains(out, "Warning: equipment [barbell]: 2 rows match by name") {
		t.Fatalf("catalog sync = %q, %v", out, err)
	}

	if n := countRows(t, pool, "SELECT count(*) FROM equipment WHERE slug IS NULL"); n != 1 {
		t.Errorf("unmanaged equipment = %d, want 1", n)
	}
}

func TestCatalogSyncRejectsBadArguments(t *testing.T) {
	t.Parallel()

	for _, args := range [][]string{{"catalog"}, {"catalog", "load"}, {"catalog", "sync", "--force"}, {"catalog", "sync", "extra"}} {
		if err := run(t.Context(), args, map[string]string{}, io.Discard, io.Discard); !errors.Is(err, errUsage) {
			t.Errorf("run(%q) = %v, want a usage error", args, err)
		}
	}
}

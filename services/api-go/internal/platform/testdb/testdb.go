// Package testdb gives each test its own Postgres database, so tests that
// touch the database can run with t.Parallel().
//
// The first New in a test binary prepares a template database with every
// migration applied; each New then clones it with CREATE DATABASE … TEMPLATE,
// which takes milliseconds. The server is a testcontainers Postgres started
// for the test binary, or the server TEST_DATABASE_URL points at (the compose
// postgres, for a faster local loop). Databases are named per test binary, so
// packages testing in parallel against one server never share one.
//
// A package using testdb declares
//
//	func TestMain(m *testing.M) { testdb.Main(m) }
//
// so the template and the container are removed when its tests finish.
package testdb

import (
	"context"
	"crypto/rand"
	"errors"
	"fmt"
	"net/url"
	"os"
	"strings"
	"sync"
	"testing"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/jackc/pgx/v5/stdlib"
	"github.com/testcontainers/testcontainers-go"
	tcpostgres "github.com/testcontainers/testcontainers-go/modules/postgres"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/migrations"
)

// image is the Postgres the container runs; it matches docker-compose.yml.
const image = "postgres:17"

// server is the per-binary state: the admin connection and the template.
type server struct {
	once sync.Once
	err  error

	adminURL  string
	template  string
	container testcontainers.Container

	// CREATE DATABASE … TEMPLATE fails while another session is copying
	// the same template, so clones are created one at a time.
	mu sync.Mutex
}

// Per test binary: Main and New run in one process, and each package's tests
// are their own binary.
var (
	srv     server
	mainRan bool
)

// Main runs the package's tests, then drops the template database and stops
// the container. Call it from TestMain.
func Main(m *testing.M) {
	mainRan = true

	// Since Go 1.15 the test binary exits with m.Run's code when TestMain
	// returns, so the teardown needs no os.Exit.
	m.Run()
	srv.teardown()
}

// New returns a pool on a fresh database with every migration applied. The
// database is dropped when the test ends.
func New(tb testing.TB) *pgxpool.Pool {
	tb.Helper()

	srv.once.Do(srv.setup)

	if srv.err != nil {
		tb.Fatalf("testdb: %v", srv.err)
	}

	return srv.clone(tb, srv.template)
}

// NewEmpty returns a pool on a fresh database with no migrations applied, for
// tests of the migrations themselves. The database is dropped when the test
// ends; pool.Config().ConnString() is its URL.
func NewEmpty(tb testing.TB) *pgxpool.Pool {
	tb.Helper()

	srv.once.Do(srv.setup)

	if srv.err != nil {
		tb.Fatalf("testdb: %v", srv.err)
	}

	return srv.clone(tb, "template0")
}

func (s *server) setup() {
	if !mainRan {
		s.err = errors.New("the package has no TestMain calling testdb.Main")

		return
	}

	ctx := context.Background()

	s.adminURL = os.Getenv("TEST_DATABASE_URL")
	if s.adminURL == "" {
		s.err = s.startContainer(ctx)
		if s.err != nil {
			return
		}
	}

	s.template = "template_" + randomSuffix()
	if s.err = s.exec(ctx, "CREATE DATABASE "+s.template); s.err != nil {
		return
	}

	templateURL, err := withDatabase(s.adminURL, s.template)
	if err != nil {
		s.err = err

		return
	}

	s.err = migrate(ctx, templateURL)
}

func (s *server) startContainer(ctx context.Context) error {
	container, err := tcpostgres.Run(ctx, image,
		tcpostgres.WithDatabase("postgres"),
		tcpostgres.WithUsername("postgres"),
		tcpostgres.WithPassword("postgres"),
		tcpostgres.BasicWaitStrategies(),
	)
	if container != nil {
		s.container = container
	}

	if err != nil {
		return fmt.Errorf("start %s: %w", image, err)
	}

	s.adminURL, err = container.ConnectionString(ctx, "sslmode=disable")
	if err != nil {
		return fmt.Errorf("container url: %w", err)
	}

	return nil
}

// migrate applies every migration to the database at dbURL.
func migrate(ctx context.Context, dbURL string) error {
	pool, err := postgres.Open(ctx, dbURL)
	if err != nil {
		return err
	}
	defer pool.Close()

	db := stdlib.OpenDBFromPool(pool)
	defer db.Close()

	migrator, err := postgres.NewMigrator(db, migrations.FS, migrations.Go()...)
	if err != nil {
		return err
	}

	if _, err := migrator.Up(ctx); err != nil {
		return fmt.Errorf("migrate template: %w", err)
	}

	return nil
}

func (s *server) clone(tb testing.TB, template string) *pgxpool.Pool {
	tb.Helper()

	ctx := context.Background()
	name := "test_" + randomSuffix()

	s.mu.Lock()
	err := s.exec(ctx, "CREATE DATABASE "+name+" TEMPLATE "+template)
	s.mu.Unlock()

	if err != nil {
		tb.Fatalf("testdb: %v", err)
	}

	tb.Cleanup(func() {
		if err := s.exec(context.Background(), "DROP DATABASE "+name+" WITH (FORCE)"); err != nil {
			tb.Errorf("testdb: %v", err)
		}
	})

	dbURL, err := withDatabase(s.adminURL, name)
	if err != nil {
		tb.Fatalf("testdb: %v", err)
	}

	pool, err := postgres.Open(ctx, dbURL)
	if err != nil {
		tb.Fatalf("testdb: %v", err)
	}

	tb.Cleanup(pool.Close)

	return pool
}

func (s *server) teardown() {
	ctx := context.Background()

	if s.template != "" && s.err == nil {
		if err := s.exec(ctx, "DROP DATABASE "+s.template+" WITH (FORCE)"); err != nil {
			fmt.Fprintln(os.Stderr, "testdb:", err)
		}
	}

	if s.container != nil {
		if err := testcontainers.TerminateContainer(s.container); err != nil {
			fmt.Fprintln(os.Stderr, "testdb:", err)
		}
	}
}

// exec runs one statement on a short-lived admin connection. CREATE and DROP
// DATABASE cannot run in a transaction, so no pool is involved.
func (s *server) exec(ctx context.Context, statement string) error {
	conn, err := pgx.Connect(ctx, s.adminURL)
	if err != nil {
		return fmt.Errorf("connect: %w", err)
	}
	defer conn.Close(ctx)

	if _, err := conn.Exec(ctx, statement); err != nil {
		return fmt.Errorf("%s: %w", statement, err)
	}

	return nil
}

// withDatabase returns serverURL pointing at the database name. The server
// URL must be a postgres:// URL, not a key=value string.
func withDatabase(serverURL, name string) (string, error) {
	u, err := url.Parse(serverURL)
	if err != nil || u.Scheme == "" {
		return "", errors.New("TEST_DATABASE_URL must be a postgres:// URL")
	}

	u.Path = "/" + name

	return u.String(), nil
}

// randomSuffix returns 12 lowercase letters and digits, so database names
// need no quoting and never collide between test binaries.
func randomSuffix() string {
	return strings.ToLower(rand.Text()[:12])
}

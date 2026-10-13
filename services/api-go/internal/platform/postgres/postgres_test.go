package postgres_test

import (
	"context"
	"errors"
	"fmt"
	"slices"
	"testing"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgconn"
	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/jackc/pgx/v5/stdlib"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
	"github.com/mwazovzky/my-workouts-online/services/api-go/migrations"
)

func TestMain(m *testing.M) { testdb.Main(m) }

var errForced = errors.New("forced")

func TestInTx(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name      string
		fn        func(ctx context.Context, tx *postgres.Transactor) error
		wantErr   error
		wantPanic bool
		wantRows  int
	}{
		{
			name:     "commits when fn returns nil",
			fn:       insertRole,
			wantRows: 1,
		},
		{
			name: "rolls back when fn returns an error",
			fn: func(ctx context.Context, tx *postgres.Transactor) error {
				if err := insertRole(ctx, tx); err != nil {
					return err
				}

				return errForced
			},
			wantErr: errForced,
		},
		{
			name: "rolls back and re-panics when fn panics",
			fn: func(ctx context.Context, tx *postgres.Transactor) error {
				if err := insertRole(ctx, tx); err != nil {
					return err
				}

				panic("boom")
			},
			wantPanic: true,
		},
		{
			name: "a nested call joins the outer transaction",
			fn: func(ctx context.Context, tx *postgres.Transactor) error {
				if err := tx.InTx(ctx, func(ctx context.Context) error { return insertRole(ctx, tx) }); err != nil {
					return err
				}

				return errForced
			},
			wantErr: errForced,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			pool := testdb.New(t)
			tx := postgres.NewTransactor(pool)

			panicked, err := runInTx(t.Context(), tx, tt.fn)
			if panicked != tt.wantPanic {
				t.Errorf("panicked = %v, want %v", panicked, tt.wantPanic)
			}

			if !errors.Is(err, tt.wantErr) {
				t.Errorf("InTx() error = %v, want %v", err, tt.wantErr)
			}

			if got := countRoles(t, pool, "Coach"); got != tt.wantRows {
				t.Errorf("rows after InTx = %d, want %d", got, tt.wantRows)
			}
		})
	}
}

func TestInTxFailsToBeginOnAClosedPool(t *testing.T) {
	t.Parallel()

	pool := testdb.New(t)
	pool.Close()

	err := postgres.NewTransactor(pool).InTx(t.Context(), func(context.Context) error { return nil })
	if err == nil {
		t.Error("InTx() error = nil, want a begin error")
	}
}

func TestQuerierUsesThePoolOutsideATransaction(t *testing.T) {
	t.Parallel()

	pool := testdb.New(t)

	if err := insertRole(t.Context(), postgres.NewTransactor(pool)); err != nil {
		t.Fatal(err)
	}

	if got := countRoles(t, pool, "Coach"); got != 1 {
		t.Errorf("rows = %d, want 1", got)
	}
}

func TestMapErrorOnRealViolations(t *testing.T) {
	t.Parallel()

	pool := testdb.New(t)
	ctx := t.Context()

	_, err := pool.Exec(ctx, "INSERT INTO roles (name) VALUES ('Admin')")
	assertConflict(t, err, "roles_name_key")

	_, err = pool.Exec(ctx, "INSERT INTO role_user (user_id, role_id) VALUES (999, 1)")
	assertConflict(t, err, "role_user_user_id_fkey")

	var name string
	err = postgres.MapError(pool.QueryRow(ctx, "SELECT name FROM roles WHERE id = 999").Scan(&name))
	if !apperr.HasKind(err, apperr.KindNotFound) {
		t.Errorf("MapError(no rows) = %v, want KindNotFound", err)
	}
}

func TestMapErrorPassesOtherErrorsThrough(t *testing.T) {
	t.Parallel()

	other := &pgconn.PgError{Code: "42P01", Message: "undefined table"}

	for _, err := range []error{nil, errForced, other} {
		if got := postgres.MapError(err); !errors.Is(got, err) || apperr.HasKind(got, apperr.KindConflict) {
			t.Errorf("MapError(%v) = %v, want it unchanged", err, got)
		}
	}

	if got := postgres.ConstraintName(errForced); got != "" {
		t.Errorf("ConstraintName(plain error) = %q, want empty", got)
	}
}

func TestReadinessCheck(t *testing.T) {
	t.Parallel()

	pool := testdb.New(t)
	check := postgres.ReadinessCheck(pool)

	if err := check(t.Context()); err != nil {
		t.Errorf("check() = %v, want nil", err)
	}

	pool.Close()

	if err := check(t.Context()); err == nil {
		t.Error("check() on a closed pool = nil, want an error")
	}
}

func TestOpenRejectsAnInvalidURLWithoutEchoingIt(t *testing.T) {
	t.Parallel()

	_, err := postgres.Open(t.Context(), "postgres://user:s3cret@host:notaport/db")
	if err == nil {
		t.Fatal("Open() error = nil, want an error")
	}

	if got := err.Error(); got != "postgres: invalid DATABASE_URL" {
		t.Errorf("Open() error = %q, want it not to repeat the URL", got)
	}
}

func TestMigrationsGoDownToZeroAndUpAgain(t *testing.T) {
	t.Parallel()

	pool := testdb.NewEmpty(t)
	db := stdlib.OpenDBFromPool(pool)
	t.Cleanup(func() { _ = db.Close() })

	migrator, err := postgres.NewMigrator(db, migrations.FS, migrations.Go()...)
	if err != nil {
		t.Fatal(err)
	}

	ctx := t.Context()

	for _, step := range []struct {
		name string
		run  func() error
		want []string
	}{
		{"up", func() error { _, err := migrator.Up(ctx); return err }, allTables},
		{"down to zero", func() error { _, err := migrator.DownTo(ctx, 0); return err }, nil},
		{"up again", func() error { _, err := migrator.Up(ctx); return err }, allTables},
	} {
		if err := step.run(); err != nil {
			t.Fatalf("%s: %v", step.name, err)
		}

		if got := publicTables(t, pool); fmt.Sprint(got) != fmt.Sprint(step.want) {
			t.Errorf("after %s tables = %v, want %v", step.name, got, step.want)
		}
	}

	version, err := migrator.GetDBVersion(ctx)
	if err != nil || version != 5 {
		t.Errorf("GetDBVersion() = %d, %v; want 5", version, err)
	}
}

// allTables are the tables the migrations create, River's included, sorted. goose_db_version is left out: goose keeps it at version 0.
var allTables = []string{
	"categories", "category_exercise", "equipment", "exercises", "inbound_emails",
	"password_reset_tokens", "program_user", "program_workout_template", "programs",
	"refresh_tokens", "river_job", "river_leader", "river_migration", "river_notification",
	"river_queue", "role_user", "roles", "template_activities",
	"template_sets", "users", "workout_activities", "workout_sets", "workout_templates", "workouts",
}

func publicTables(t *testing.T, pool *pgxpool.Pool) []string {
	t.Helper()

	rows, err := pool.Query(t.Context(),
		"SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'goose_db_version'")
	if err != nil {
		t.Fatal(err)
	}

	tables, err := pgx.CollectRows(rows, pgx.RowTo[string])
	if err != nil {
		t.Fatal(err)
	}

	// Sorted in Go: the database's collation may order "_" differently.
	slices.Sort(tables)

	return tables
}

func insertRole(ctx context.Context, tx *postgres.Transactor) error {
	_, err := tx.Querier(ctx).Exec(ctx, "INSERT INTO roles (name) VALUES ('Coach')")

	return err
}

func countRoles(t *testing.T, pool *pgxpool.Pool, name string) int {
	t.Helper()

	var n int
	if err := pool.QueryRow(t.Context(), "SELECT count(*) FROM roles WHERE name = $1", name).Scan(&n); err != nil {
		t.Fatal(err)
	}

	return n
}

// runInTx calls InTx and reports whether it panicked.
func runInTx(ctx context.Context, tx *postgres.Transactor, fn func(context.Context, *postgres.Transactor) error) (panicked bool, err error) {
	defer func() {
		if recover() != nil {
			panicked = true
		}
	}()

	return false, tx.InTx(ctx, func(ctx context.Context) error { return fn(ctx, tx) })
}

func assertConflict(t *testing.T, err error, constraint string) {
	t.Helper()

	mapped := postgres.MapError(err)
	if !apperr.HasKind(mapped, apperr.KindConflict) {
		t.Errorf("MapError(%v) = %v, want KindConflict", err, mapped)
	}

	if got := postgres.ConstraintName(mapped); got != constraint {
		t.Errorf("ConstraintName() = %q, want %q", got, constraint)
	}
}

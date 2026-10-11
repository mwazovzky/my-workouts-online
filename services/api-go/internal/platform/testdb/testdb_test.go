package testdb_test

import (
	"testing"

	"github.com/jackc/pgx/v5/pgxpool"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

func TestEachTestGetsItsOwnMigratedDatabase(t *testing.T) {
	t.Parallel()

	first, second := testdb.New(t), testdb.New(t)

	if first.Config().ConnConfig.Database == second.Config().ConnConfig.Database {
		t.Fatal("two calls returned the same database")
	}

	if _, err := first.Exec(t.Context(), "INSERT INTO roles (name) VALUES ('Coach')"); err != nil {
		t.Fatal(err)
	}

	if got := roles(t, first); got != 3 {
		t.Errorf("roles in the first database = %d, want the 2 seeded plus 1", got)
	}

	if got := roles(t, second); got != 2 {
		t.Errorf("roles in the second database = %d, want the 2 seeded", got)
	}
}

func TestNewEmptyHasNoSchema(t *testing.T) {
	t.Parallel()

	var tables int
	if err := testdb.NewEmpty(t).QueryRow(t.Context(),
		"SELECT count(*) FROM pg_tables WHERE schemaname = 'public'").Scan(&tables); err != nil {
		t.Fatal(err)
	}

	if tables != 0 {
		t.Errorf("tables = %d, want 0", tables)
	}
}

func roles(t *testing.T, pool *pgxpool.Pool) int {
	t.Helper()

	var n int
	if err := pool.QueryRow(t.Context(), "SELECT count(*) FROM roles").Scan(&n); err != nil {
		t.Fatal(err)
	}

	return n
}

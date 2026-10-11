// Package postgres is the shared Postgres infrastructure: the connection
// pool, the migration runner, the Transactor every module's repositories run
// in, the pgx error mapper and the readiness check. Modules keep their own
// queries (sqlc packages under adapters/postgres); this package knows no
// table.
package postgres

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"io/fs"

	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/pressly/goose/v3"
	"github.com/pressly/goose/v3/lock"
)

// Open connects a pool to url and pings it, so a wrong URL or an unreachable
// server fails at startup. Pool settings come from the URL
// (pool_max_conns=…, see pgxpool.ParseConfig).
func Open(ctx context.Context, url string) (*pgxpool.Pool, error) {
	cfg, err := pgxpool.ParseConfig(url)
	if err != nil {
		// The parse error repeats the URL, password included.
		return nil, errors.New("postgres: invalid DATABASE_URL")
	}

	pool, err := pgxpool.NewWithConfig(ctx, cfg)
	if err != nil {
		return nil, fmt.Errorf("postgres: %w", err)
	}

	if err := pool.Ping(ctx); err != nil {
		pool.Close()

		return nil, fmt.Errorf("postgres: ping: %w", err)
	}

	return pool, nil
}

// NewMigrator returns a goose provider over the SQL migrations in fsys plus
// the Go migrations given. Only these migrations count (goose's global
// registry is ignored), and a session-level advisory lock keeps two
// concurrent runs from applying the same migration.
func NewMigrator(db *sql.DB, fsys fs.FS, goMigrations ...*goose.Migration) (*goose.Provider, error) {
	locker, err := lock.NewPostgresSessionLocker()
	if err != nil {
		return nil, fmt.Errorf("migrator lock: %w", err)
	}

	provider, err := goose.NewProvider(goose.DialectPostgres, db, fsys,
		goose.WithDisableGlobalRegistry(true),
		goose.WithGoMigrations(goMigrations...),
		goose.WithSessionLocker(locker),
	)
	if err != nil {
		return nil, fmt.Errorf("migrator: %w", err)
	}

	return provider, nil
}

// ReadinessCheck returns the /health/ready check for the database: a round
// trip through the pool, not just an open connection.
func ReadinessCheck(pool *pgxpool.Pool) func(context.Context) error {
	return func(ctx context.Context) error {
		var one int
		if err := pool.QueryRow(ctx, "SELECT 1").Scan(&one); err != nil {
			return fmt.Errorf("postgres: %w", err)
		}

		return nil
	}
}

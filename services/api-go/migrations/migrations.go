// Package migrations holds the database schema: goose SQL migrations, which
// are also sqlc's schema input, plus Go migrations for schemas a library
// owns. `workouts migrate` and the test databases apply them through
// platform/postgres.NewMigrator.
package migrations

import (
	"context"
	"database/sql"
	"embed"
	"fmt"
	"log/slog"

	"github.com/pressly/goose/v3"
	"github.com/riverqueue/river/riverdriver/riverdatabasesql"
	"github.com/riverqueue/river/rivermigrate"
)

// FS holds the SQL migrations.
//
//go:embed *.sql
var FS embed.FS

// riverVersion is the River schema version migration 00004 installs. A River
// upgrade that ships a new schema version gets its own migration that moves
// the target forward, so databases already past 00004 receive it too.
const riverVersion = 8

// Go returns the migrations written in Go, to register next to FS.
func Go() []*goose.Migration {
	return []*goose.Migration{
		// 00004: River's job queue schema, through River's own migrator, so a
		// deploy still has a single migrate step. Outside goose's transaction:
		// River runs each of its migrations in its own, because some cannot
		// share one. A failure part-way is safe to rerun; River skips the
		// versions it recorded.
		goose.NewGoMigration(4,
			&goose.GoFunc{RunDB: func(ctx context.Context, db *sql.DB) error {
				return migrateRiver(ctx, db, rivermigrate.DirectionUp, riverVersion)
			}},
			&goose.GoFunc{RunDB: func(ctx context.Context, db *sql.DB) error {
				// -1 removes every River migration, its version table included.
				return migrateRiver(ctx, db, rivermigrate.DirectionDown, -1)
			}},
		),
	}
}

func migrateRiver(ctx context.Context, db *sql.DB, direction rivermigrate.Direction, target int) error {
	migrator, err := rivermigrate.New(riverdatabasesql.New(db), &rivermigrate.Config{Logger: slog.New(slog.DiscardHandler)})
	if err != nil {
		return fmt.Errorf("river migrator: %w", err)
	}

	if _, err := migrator.Migrate(ctx, direction, &rivermigrate.MigrateOpts{TargetVersion: target}); err != nil {
		return fmt.Errorf("river migrate %s: %w", direction, err)
	}

	return nil
}

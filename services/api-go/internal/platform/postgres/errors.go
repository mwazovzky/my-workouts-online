package postgres

import (
	"errors"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgconn"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
)

// SQLSTATE codes the mapper translates.
const (
	foreignKeyViolation = "23503"
	uniqueViolation     = "23505"
)

// MapError translates pgx errors into apperr kinds: no rows is NotFound, a
// unique or foreign-key violation is Conflict. The pgx error stays wrapped,
// so ConstraintName still finds the constraint. Other errors, and nil, pass
// through unchanged.
func MapError(err error) error {
	if errors.Is(err, pgx.ErrNoRows) {
		return apperr.NotFound("record not found", err)
	}

	var pgErr *pgconn.PgError
	if !errors.As(err, &pgErr) {
		return err
	}

	switch pgErr.Code {
	case uniqueViolation:
		return apperr.Conflict("duplicate key "+pgErr.ConstraintName, err)
	case foreignKeyViolation:
		return apperr.Conflict("foreign key "+pgErr.ConstraintName, err)
	default:
		return err
	}
}

// ConstraintName returns the constraint a Postgres error in err's chain
// violated, or "" when there is none. Repositories switch on it to turn a
// Conflict into a field error (users_email_key → email not_unique).
func ConstraintName(err error) string {
	var pgErr *pgconn.PgError
	if errors.As(err, &pgErr) {
		return pgErr.ConstraintName
	}

	return ""
}

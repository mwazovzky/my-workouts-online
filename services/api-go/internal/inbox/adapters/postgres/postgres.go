// Package postgres implements the inbox's receipt store over sqlc
// (sql/inbox).
package postgres

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// Receipts implements ports.Receipts on inbound_emails.
type Receipts struct {
	tx *postgres.Transactor
}

var _ ports.Receipts = (*Receipts)(nil)

// NewReceipts returns the Receipts repository.
func NewReceipts(tx *postgres.Transactor) *Receipts {
	return &Receipts{tx: tx}
}

func (r *Receipts) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(r.tx.Querier(ctx))
}

// Record implements ports.Receipts.
func (r *Receipts) Record(ctx context.Context, id string, at time.Time) (bool, error) {
	inserted, err := r.q(ctx).RecordInboundEmail(ctx, id, at)
	if err != nil {
		return false, postgres.MapError(err)
	}

	return inserted == 1, nil
}

// Prune implements ports.Receipts.
func (r *Receipts) Prune(ctx context.Context, before time.Time) error {
	return postgres.MapError(r.q(ctx).PruneInboundEmails(ctx, before))
}

package postgres

import (
	"context"
	"errors"
	"fmt"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgconn"
	"github.com/jackc/pgx/v5/pgxpool"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/txn"
)

// DBTX is what sqlc's generated Queries run on: the pool, or a transaction.
// It matches the DBTX interface in every sqlcgen package.
type DBTX interface {
	Exec(ctx context.Context, sql string, args ...any) (pgconn.CommandTag, error)
	Query(ctx context.Context, sql string, args ...any) (pgx.Rows, error)
	QueryRow(ctx context.Context, sql string, args ...any) pgx.Row
}

// Transactor runs units of work in a pgx transaction carried by the context.
// Repositories take their DBTX from Querier, so the same repository code runs
// inside and outside a transaction.
type Transactor struct {
	pool *pgxpool.Pool
}

var _ txn.Transactor = (*Transactor)(nil)

// NewTransactor returns a Transactor over pool.
func NewTransactor(pool *pgxpool.Pool) *Transactor {
	return &Transactor{pool: pool}
}

type txKey struct{}

// InTx runs fn in a transaction: commit when fn returns nil, rollback when it
// returns an error or panics (the panic is re-raised). When ctx already
// carries a transaction, fn joins it, and the outermost InTx decides.
func (t *Transactor) InTx(ctx context.Context, fn func(ctx context.Context) error) error {
	if _, ok := ctx.Value(txKey{}).(pgx.Tx); ok {
		return fn(ctx)
	}

	tx, err := t.pool.Begin(ctx)
	if err != nil {
		return fmt.Errorf("begin: %w", err)
	}

	// Roll back even when ctx is already cancelled.
	rollbackCtx := context.WithoutCancel(ctx)

	defer func() {
		if p := recover(); p != nil {
			_ = tx.Rollback(rollbackCtx)

			panic(p)
		}
	}()

	if err := fn(context.WithValue(ctx, txKey{}, tx)); err != nil {
		if rbErr := tx.Rollback(rollbackCtx); rbErr != nil {
			return errors.Join(err, fmt.Errorf("rollback: %w", rbErr))
		}

		return err
	}

	if err := tx.Commit(ctx); err != nil {
		return fmt.Errorf("commit: %w", err)
	}

	return nil
}

// TxFrom returns the transaction ctx carries, for a library that takes a
// pgx.Tx itself (River's InsertTx joins the unit of work through it).
func TxFrom(ctx context.Context) (pgx.Tx, bool) {
	tx, ok := ctx.Value(txKey{}).(pgx.Tx)

	return tx, ok
}

// Querier returns the transaction ctx carries, or the pool outside one.
func (t *Transactor) Querier(ctx context.Context) DBTX {
	if tx, ok := ctx.Value(txKey{}).(pgx.Tx); ok {
		return tx
	}

	return t.pool
}

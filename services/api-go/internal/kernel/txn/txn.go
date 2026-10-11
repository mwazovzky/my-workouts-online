// Package txn is the port use cases run a unit of work through. The postgres
// adapter carries the transaction in the context, so repositories called
// inside fn join it without a transaction parameter.
package txn

import "context"

// Transactor runs fn in one transaction: committed when fn returns nil,
// rolled back when it returns an error or panics. A call inside fn joins the
// outer transaction instead of opening another.
type Transactor interface {
	InTx(ctx context.Context, fn func(ctx context.Context) error) error
}

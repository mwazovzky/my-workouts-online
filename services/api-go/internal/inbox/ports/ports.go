// Package ports declares what the inbox use cases need from the outside.
package ports

import (
	"context"
	"errors"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/domain"
)

// ErrEmailNotFound is a Source answer for an id the provider does not know.
// Retrying cannot help, so the job is cancelled.
var ErrEmailNotFound = errors.New("inbound email not found")

// Source reads received emails from the provider.
type Source interface {
	// Find returns the email with its attachments' contents, or
	// ErrEmailNotFound. Any other error is worth retrying.
	Find(ctx context.Context, id string) (domain.Email, error)
}

// Receipts remembers which email ids were queued.
type Receipts interface {
	// Record stores id and reports whether it is new.
	Record(ctx context.Context, id string, at time.Time) (bool, error)
	// Prune forgets ids recorded before before.
	Prune(ctx context.Context, before time.Time) error
}

// Queue schedules background work. Called inside a transaction, the job is
// inserted in it and runs only if it commits.
type Queue interface {
	EnqueueForward(ctx context.Context, emailID string) error
}

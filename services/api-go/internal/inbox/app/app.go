// Package app holds the inbox use cases: Receive, which the webhook calls,
// and Forward, which the worker runs.
package app

import (
	"context"
	"fmt"
	"log/slog"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/txn"
)

// ReceiptRetention is how long a received id is remembered, as PHP's
// ForwardInboundEmail caches a forwarded id for a week.
const ReceiptRetention = 7 * 24 * time.Hour

// Deps are the Service's dependencies.
type Deps struct {
	Source   ports.Source
	Receipts ports.Receipts
	Queue    ports.Queue
	Mailer   mail.Mailer
	Tx       txn.Transactor
	Clock    clock.Clock
	Logger   *slog.Logger
	// ForwardTo is MAIL_INBOUND_FORWARD_TO; blank forwards nothing.
	ForwardTo string
	// AppName is APP_NAME, shown in the sender name.
	AppName string
}

// Service runs the inbox use cases.
type Service struct {
	deps Deps
}

// New returns the Service.
func New(deps Deps) *Service {
	return &Service{deps: deps}
}

// Receive queues forwarding for a received email, once per id: the id is
// recorded and the job inserted in one transaction, and an id already
// recorded queues nothing. Ids older than ReceiptRetention are pruned first.
func (s *Service) Receive(ctx context.Context, emailID string) error {
	now := s.deps.Clock.Now()

	return s.deps.Tx.InTx(ctx, func(ctx context.Context) error {
		if err := s.deps.Receipts.Prune(ctx, now.Add(-ReceiptRetention)); err != nil {
			return fmt.Errorf("prune receipts: %w", err)
		}

		isNew, err := s.deps.Receipts.Record(ctx, emailID, now)
		if err != nil {
			return fmt.Errorf("record receipt: %w", err)
		}

		if !isNew {
			s.deps.Logger.InfoContext(ctx, "inbound email already queued", "email_id", emailID)

			return nil
		}

		if err := s.deps.Queue.EnqueueForward(ctx, emailID); err != nil {
			return fmt.Errorf("enqueue forward: %w", err)
		}

		return nil
	})
}

// Forward relays a received email to the support inbox. Without a forwarding
// address it logs a warning and does nothing, so the job completes. A
// ports.ErrEmailNotFound from the source is returned wrapped.
func (s *Service) Forward(ctx context.Context, emailID string) error {
	if s.deps.ForwardTo == "" {
		s.deps.Logger.WarnContext(ctx, "Inbound email not forwarded: MAIL_INBOUND_FORWARD_TO is not set.", "email_id", emailID)

		return nil
	}

	email, err := s.deps.Source.Find(ctx, emailID)
	if err != nil {
		return fmt.Errorf("find inbound email %s: %w", emailID, err)
	}

	if err := s.deps.Mailer.Send(ctx, domain.Forward(email, s.deps.ForwardTo, s.deps.AppName)); err != nil {
		return fmt.Errorf("forward inbound email %s: %w", emailID, err)
	}

	return nil
}

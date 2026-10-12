// Package mail holds the adapters for the kernel/mail port that need no
// provider: Log writes each message to the structured log (the dev stack
// and CI, as Laravel's MAIL_MAILER=log does), Fake keeps them for tests.
package mail

import (
	"context"
	"log/slog"
	"sync"

	kernelmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
)

// Log logs every message, text body included, so links can be followed in
// development. It never delivers anything.
type Log struct {
	logger *slog.Logger
}

var _ kernelmail.Mailer = Log{}

// NewLog returns a Log mailer.
func NewLog(logger *slog.Logger) Log {
	return Log{logger: logger}
}

// Send implements kernelmail.Mailer.
func (l Log) Send(ctx context.Context, msg kernelmail.Message) error {
	l.logger.InfoContext(ctx, "mail", "to", msg.To, "subject", msg.Subject, "text", msg.Text)

	return nil
}

// Fake records messages instead of sending them. It is safe for concurrent
// use; Err, when set, is returned by every Send.
type Fake struct {
	mu   sync.Mutex
	sent []kernelmail.Message
	Err  error
}

var _ kernelmail.Mailer = (*Fake)(nil)

// Send implements kernelmail.Mailer.
func (f *Fake) Send(_ context.Context, msg kernelmail.Message) error {
	f.mu.Lock()
	defer f.mu.Unlock()

	if f.Err != nil {
		return f.Err
	}

	f.sent = append(f.sent, msg)

	return nil
}

// Sent returns the messages sent to address, oldest first.
func (f *Fake) Sent(address string) []kernelmail.Message {
	f.mu.Lock()
	defer f.mu.Unlock()

	var out []kernelmail.Message

	for _, msg := range f.sent {
		if msg.To == address {
			out = append(out, msg)
		}
	}

	return out
}

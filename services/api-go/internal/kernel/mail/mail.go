// Package mail is the port for outgoing email. Modules compose a Message;
// platform/mail delivers it (the log adapter in development and tests, the
// fake in tests that read what was sent, Resend from story 11).
package mail

import "context"

// Message is one email.
type Message struct {
	To      string
	Subject string
	// Text and HTML are the same content; clients show one of them.
	Text string
	HTML string
}

// Mailer sends email.
type Mailer interface {
	Send(ctx context.Context, msg Message) error
}

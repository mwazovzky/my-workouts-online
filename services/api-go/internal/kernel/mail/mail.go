// Package mail is the port for outgoing email. Modules compose a Message;
// platform/mail delivers it (the log adapter in development and tests, the
// fake in tests that read what was sent) and platform/resend sends it
// through Resend in production.
package mail

import (
	"context"
	"strings"
)

// Message is one email. It always leaves from the configured sender address
// (MAIL_FROM_ADDRESS).
type Message struct {
	To string
	// FromName replaces the configured sender name (MAIL_FROM_NAME) when set.
	FromName string
	// ReplyTo is where replies go; empty means the sender.
	ReplyTo []Address
	Subject string
	// Text and HTML are the same content; clients show one of them. Either
	// may be empty.
	Text        string
	HTML        string
	Attachments []Attachment
}

// Address is a mailbox with an optional display name.
type Address struct {
	Name  string
	Email string
}

// String formats the address as "Name <email>", or the bare email without a
// name. A name with characters special in an address header is quoted.
func (a Address) String() string {
	if a.Name == "" {
		return a.Email
	}

	name := a.Name
	if strings.ContainsAny(name, `()<>[]:;@\,."`) {
		name = `"` + strings.NewReplacer(`\`, `\\`, `"`, `\"`).Replace(name) + `"`
	}

	return name + " <" + a.Email + ">"
}

// Attachment is a file sent with a message.
type Attachment struct {
	Filename    string
	ContentType string
	Content     []byte
}

// Mailer sends email.
type Mailer interface {
	Send(ctx context.Context, msg Message) error
}

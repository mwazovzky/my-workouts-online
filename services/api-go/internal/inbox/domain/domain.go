// Package domain holds the received email and the rules for relaying it.
package domain

import (
	"html"
	"net/mail"
	"strings"

	kernelmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
)

// Email is an email received on the domain, attachment contents included.
type Email struct {
	ID      string
	From    string
	To      []string
	Cc      []string
	ReplyTo []string
	Subject string
	HTML    string
	Text    string
	// ReceivedAt is the time as Resend reports it, shown as is.
	ReceivedAt  string
	Attachments []Attachment
}

// Attachment is a received file.
type Attachment struct {
	Filename    string
	ContentType string
	Content     []byte
}

// Forward builds the message relayed to the support inbox at to, as PHP's
// ForwardedInboundEmail. It is sent from the app's own address (the original
// sender's domain would fail SPF and DMARC) under the sender's name plus
// " via <appName>"; replies go to the original Reply-To, or else the sender.
// The body is a summary of the original headers followed by the original
// HTML, or the escaped text when there is no HTML.
func Forward(email Email, to, appName string) kernelmail.Message {
	sender := parseAddress(email.From)

	name := sender.Name
	if name == "" {
		name = sender.Email
	}

	replyTo := email.ReplyTo
	if len(replyTo) == 0 {
		replyTo = []string{email.From}
	}

	addresses := make([]kernelmail.Address, 0, len(replyTo))
	for _, address := range replyTo {
		addresses = append(addresses, parseAddress(address))
	}

	subject := email.Subject
	if subject == "" {
		subject = "(no subject)"
	}

	attachments := make([]kernelmail.Attachment, 0, len(email.Attachments))
	for _, attachment := range email.Attachments {
		attachments = append(attachments, kernelmail.Attachment(attachment))
	}

	return kernelmail.Message{
		To:          to,
		FromName:    name + " via " + appName,
		ReplyTo:     addresses,
		Subject:     subject,
		HTML:        summary(email) + body(email),
		Attachments: attachments,
	}
}

// summary lists the original From, To, Cc and Date, leaving out empty ones.
func summary(email Email) string {
	rows := []struct{ label, value string }{
		{"From", email.From},
		{"To", strings.Join(email.To, ", ")},
		{"Cc", strings.Join(email.Cc, ", ")},
		{"Date", email.ReceivedAt},
	}

	lines := make([]string, 0, len(rows))
	for _, row := range rows {
		if row.value != "" {
			lines = append(lines, "<strong>"+row.label+":</strong> "+html.EscapeString(row.value))
		}
	}

	return `<div style="border-bottom:1px solid #ccc;color:#555;font:13px sans-serif;margin-bottom:16px;padding-bottom:8px">` +
		strings.Join(lines, "<br>") + "</div>"
}

func body(email Email) string {
	if strings.TrimSpace(email.HTML) != "" {
		return email.HTML
	}

	return `<div style="white-space:pre-wrap">` + html.EscapeString(email.Text) + "</div>"
}

// parseAddress reads "Name <email>" or a bare email; anything unparsable is
// kept whole as the email.
func parseAddress(raw string) kernelmail.Address {
	parsed, err := mail.ParseAddress(raw)
	if err != nil {
		return kernelmail.Address{Email: strings.TrimSpace(raw)}
	}

	return kernelmail.Address{Name: parsed.Name, Email: parsed.Address}
}

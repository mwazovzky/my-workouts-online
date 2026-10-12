// Package notify renders identity's emails (verify email, reset password)
// from embedded templates in the recipient's request locale and hands them
// to the mailer. English is the fallback for a locale without templates.
package notify

import (
	"bytes"
	"context"
	"embed"
	"fmt"
	htmltemplate "html/template"
	texttemplate "text/template"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
)

//go:embed templates
var templates embed.FS

const fallbackLocale = "en"

// Notifier implements ports.Notifier.
type Notifier struct {
	mailer  mail.Mailer
	appName string
	text    map[string]*texttemplate.Template
	html    map[string]*htmltemplate.Template
}

var _ ports.Notifier = (*Notifier)(nil)

// New parses the templates; appName signs the emails (APP_NAME).
func New(mailer mail.Mailer, appName string) (*Notifier, error) {
	n := &Notifier{
		mailer:  mailer,
		appName: appName,
		text:    map[string]*texttemplate.Template{},
		html:    map[string]*htmltemplate.Template{},
	}

	for _, kind := range []string{"verify", "reset"} {
		for _, locale := range []string{"en", "ru"} {
			key := kind + "." + locale

			text, err := texttemplate.ParseFS(templates, "templates/"+key+".txt")
			if err != nil {
				return nil, fmt.Errorf("notify: %w", err)
			}

			html, err := htmltemplate.ParseFS(templates, "templates/"+key+".html")
			if err != nil {
				return nil, fmt.Errorf("notify: %w", err)
			}

			n.text[key], n.html[key] = text, html
		}
	}

	return n, nil
}

type data struct {
	AppName string
	Link    string
	Minutes int
}

// VerifyEmail implements ports.Notifier.
func (n *Notifier) VerifyEmail(ctx context.Context, to, link, locale string) error {
	return n.send(ctx, "verify", locale, to, data{AppName: n.appName, Link: link})
}

// ResetPassword implements ports.Notifier.
func (n *Notifier) ResetPassword(ctx context.Context, to, link, locale string, expiresInMinutes int) error {
	return n.send(ctx, "reset", locale, to, data{AppName: n.appName, Link: link, Minutes: expiresInMinutes})
}

func (n *Notifier) send(ctx context.Context, kind, locale, to string, d data) error {
	key := kind + "." + locale
	if _, ok := n.text[key]; !ok {
		key = kind + "." + fallbackLocale
	}

	var subject, text, html bytes.Buffer

	if err := n.text[key].ExecuteTemplate(&subject, "subject", d); err != nil {
		return fmt.Errorf("notify %s: %w", key, err)
	}

	if err := n.text[key].ExecuteTemplate(&text, "text", d); err != nil {
		return fmt.Errorf("notify %s: %w", key, err)
	}

	if err := n.html[key].Execute(&html, d); err != nil {
		return fmt.Errorf("notify %s: %w", key, err)
	}

	return n.mailer.Send(ctx, mail.Message{To: to, Subject: subject.String(), Text: text.String(), HTML: html.String()})
}

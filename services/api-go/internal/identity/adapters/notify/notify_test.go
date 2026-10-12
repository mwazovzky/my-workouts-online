package notify_test

import (
	"strings"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/notify"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
)

const link = "https://app.example.test/verify-email/7/abc?expires=1&signature=f"

func TestRendersInTheLocale(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name, locale, wantSubject, wantText string
		send                                func(*notify.Notifier) error
	}{
		{"verify en", "en", "Verify Email Address", "verify your email address", func(n *notify.Notifier) error {
			return n.VerifyEmail(t.Context(), "ada@example.com", link, "en")
		}},
		{"verify ru", "ru", "Подтвердите адрес электронной почты", "подтвердить адрес", func(n *notify.Notifier) error {
			return n.VerifyEmail(t.Context(), "ada@example.com", link, "ru")
		}},
		{"verify falls back to en", "de", "Verify Email Address", "verify your email address", func(n *notify.Notifier) error {
			return n.VerifyEmail(t.Context(), "ada@example.com", link, "de")
		}},
		{"reset en", "en", "Reset Password Notification", "expire in 60 minutes", func(n *notify.Notifier) error {
			return n.ResetPassword(t.Context(), "ada@example.com", link, "en", 60)
		}},
		{"reset ru", "ru", "Сброс пароля", "действительна 60 мин", func(n *notify.Notifier) error {
			return n.ResetPassword(t.Context(), "ada@example.com", link, "ru", 60)
		}},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			fake := &mail.Fake{}

			n, err := notify.New(fake, "My Workouts")
			if err != nil {
				t.Fatal(err)
			}

			if err := tt.send(n); err != nil {
				t.Fatal(err)
			}

			sent := fake.Sent("ada@example.com")
			if len(sent) != 1 {
				t.Fatalf("sent %d messages, want 1", len(sent))
			}

			msg := sent[0]
			if msg.Subject != tt.wantSubject || !strings.Contains(msg.Text, tt.wantText) || !strings.Contains(msg.Text, "My Workouts") {
				t.Errorf("message = %+v", msg)
			}

			// The link is literal in the text and HTML-escaped in the HTML.
			if !strings.Contains(msg.Text, link) || !strings.Contains(msg.HTML, `href="`+strings.ReplaceAll(link, "&", "&amp;")+`"`) {
				t.Errorf("the link is missing or mangled:\n%s\n%s", msg.Text, msg.HTML)
			}
		})
	}
}

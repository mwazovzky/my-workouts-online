package domain_test

import (
	"strings"
	"testing"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/domain"
	kernelmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
)

// email is the received email of PHP's ForwardInboundEmailTest.
func email(edit func(*domain.Email)) domain.Email {
	e := domain.Email{
		ID:         "email-1",
		From:       "Jane Doe <jane@example.com>",
		To:         []string{"support@my-workouts.online"},
		Subject:    "Lost workout",
		HTML:       "<p>Hello</p>",
		Text:       "Hello",
		ReceivedAt: "2026-10-05T20:00:00.000Z",
	}
	if edit != nil {
		edit(&e)
	}

	return e
}

func TestForwardAddressesTheMessage(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name         string
		email        domain.Email
		wantFromName string
		wantReplyTo  []kernelmail.Address
		wantSubject  string
	}{
		{
			"the sender's name, replies to the sender",
			email(nil),
			"Jane Doe via My Workouts",
			[]kernelmail.Address{{Name: "Jane Doe", Email: "jane@example.com"}},
			"Lost workout",
		},
		{
			"the original Reply-To wins over the sender",
			email(func(e *domain.Email) { e.ReplyTo = []string{"team@example.com", "Ops <ops@example.com>"} }),
			"Jane Doe via My Workouts",
			[]kernelmail.Address{{Email: "team@example.com"}, {Name: "Ops", Email: "ops@example.com"}},
			"Lost workout",
		},
		{
			"a sender without a name, an empty subject",
			email(func(e *domain.Email) { e.From, e.Subject = "jane@example.com", "" }),
			"jane@example.com via My Workouts",
			[]kernelmail.Address{{Email: "jane@example.com"}},
			"(no subject)",
		},
		{
			"an unparsable sender is kept whole",
			email(func(e *domain.Email) { e.From = "not an address" }),
			"not an address via My Workouts",
			[]kernelmail.Address{{Email: "not an address"}},
			"Lost workout",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			msg := domain.Forward(tt.email, "owner@example.com", "My Workouts")

			if msg.To != "owner@example.com" || msg.FromName != tt.wantFromName || msg.Subject != tt.wantSubject {
				t.Errorf("To, FromName, Subject = %q, %q, %q; want owner@example.com, %q, %q",
					msg.To, msg.FromName, msg.Subject, tt.wantFromName, tt.wantSubject)
			}

			if diff := cmp.Diff(tt.wantReplyTo, msg.ReplyTo); diff != "" {
				t.Errorf("ReplyTo (-want +got):\n%s", diff)
			}
		})
	}
}

func TestForwardBody(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name     string
		email    domain.Email
		want     []string
		wantNone []string
	}{
		{
			"a summary of the original headers, then the original HTML",
			email(func(e *domain.Email) { e.HTML, e.Cc = "<p>My workout is gone</p>", []string{"a@x.y", "b@x.y"} }),
			[]string{
				"<strong>From:</strong> Jane Doe &lt;jane@example.com&gt;",
				"<strong>To:</strong> support@my-workouts.online",
				"<strong>Cc:</strong> a@x.y, b@x.y",
				"<strong>Date:</strong> 2026-10-05T20:00:00.000Z",
				"</div><p>My workout is gone</p>",
			},
			nil,
		},
		{
			"empty rows are left out",
			email(func(e *domain.Email) { e.ReceivedAt = "" }),
			[]string{"<strong>To:</strong>"},
			[]string{"<strong>Cc:</strong>", "<strong>Date:</strong>"},
		},
		{
			"without HTML the text is escaped",
			email(func(e *domain.Email) { e.HTML, e.Text = " ", "Hi <script>\nthanks" }),
			[]string{`<div style="white-space:pre-wrap">Hi &lt;script&gt;` + "\nthanks</div>"},
			[]string{"<script>"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			msg := domain.Forward(tt.email, "owner@example.com", "My Workouts")

			for _, want := range tt.want {
				if !strings.Contains(msg.HTML, want) {
					t.Errorf("HTML lacks %q:\n%s", want, msg.HTML)
				}
			}

			for _, unwanted := range tt.wantNone {
				if strings.Contains(msg.HTML, unwanted) {
					t.Errorf("HTML contains %q:\n%s", unwanted, msg.HTML)
				}
			}

			if msg.Text != "" {
				t.Errorf("Text = %q, want only an HTML body, as PHP sends", msg.Text)
			}
		})
	}
}

func TestForwardAttachesTheOriginalAttachments(t *testing.T) {
	t.Parallel()

	msg := domain.Forward(email(func(e *domain.Email) {
		e.Attachments = []domain.Attachment{
			{Filename: "screenshot.png", ContentType: "image/png", Content: []byte("png-bytes")},
			{Filename: "log.txt", ContentType: "text/plain", Content: []byte("log")},
		}
	}), "owner@example.com", "My Workouts")

	want := []kernelmail.Attachment{
		{Filename: "screenshot.png", ContentType: "image/png", Content: []byte("png-bytes")},
		{Filename: "log.txt", ContentType: "text/plain", Content: []byte("log")},
	}
	if diff := cmp.Diff(want, msg.Attachments); diff != "" {
		t.Errorf("Attachments (-want +got):\n%s", diff)
	}
}

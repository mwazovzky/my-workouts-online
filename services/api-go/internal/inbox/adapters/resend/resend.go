// Package resend implements the inbox's email source over the Resend
// receiving API (platform/resend), as PHP's ResendInboundEmailSource: the
// webhook carries only metadata, so the body and attachments are fetched.
package resend

import (
	"context"
	"fmt"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend"
)

// Source implements ports.Source.
type Source struct {
	client *resend.Client
}

var _ ports.Source = Source{}

// NewSource returns a Source reading through client.
func NewSource(client *resend.Client) Source {
	return Source{client: client}
}

// Find implements ports.Source. The attachments are listed and downloaded
// only when the email has any.
func (s Source) Find(ctx context.Context, id string) (domain.Email, error) {
	received, err := s.client.ReceivedEmail(ctx, id)
	if resend.IsNotFound(err) {
		return domain.Email{}, fmt.Errorf("%w: %w", ports.ErrEmailNotFound, err)
	}

	if err != nil {
		return domain.Email{}, err
	}

	email := domain.Email{
		ID:         received.ID,
		From:       received.From,
		To:         received.To,
		Cc:         received.Cc,
		ReplyTo:    received.ReplyTo,
		Subject:    received.Subject,
		HTML:       received.HTML,
		Text:       received.Text,
		ReceivedAt: received.CreatedAt,
	}

	if len(received.Attachments) == 0 {
		return email, nil
	}

	attachments, err := s.client.ReceivedAttachments(ctx, id)
	if err != nil {
		return domain.Email{}, err
	}

	for _, attachment := range attachments {
		content, err := s.client.Download(ctx, attachment.DownloadURL)
		if err != nil {
			return domain.Email{}, fmt.Errorf("attachment %s: %w", attachment.ID, err)
		}

		email.Attachments = append(email.Attachments, domain.Attachment{
			Filename: attachment.Filename, ContentType: attachment.ContentType, Content: content,
		})
	}

	return email, nil
}

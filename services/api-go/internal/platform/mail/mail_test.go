package mail_test

import (
	"bytes"
	"errors"
	"log/slog"
	"strings"
	"testing"

	kernelmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
)

func TestLogWritesTheMessage(t *testing.T) {
	t.Parallel()

	var buf bytes.Buffer
	if err := mail.NewLog(slog.New(slog.NewJSONHandler(&buf, nil))).Send(t.Context(), kernelmail.Message{
		To: "ada@example.com", Subject: "Hi", Text: "https://link",
	}); err != nil {
		t.Fatal(err)
	}

	for _, want := range []string{`"to":"ada@example.com"`, `"subject":"Hi"`, "https://link"} {
		if !strings.Contains(buf.String(), want) {
			t.Errorf("log %q lacks %q", buf.String(), want)
		}
	}
}

func TestFakeRecordsPerAddress(t *testing.T) {
	t.Parallel()

	fake := &mail.Fake{}
	for _, to := range []string{"a@x.y", "b@x.y", "a@x.y"} {
		if err := fake.Send(t.Context(), kernelmail.Message{To: to}); err != nil {
			t.Fatal(err)
		}
	}

	if got := len(fake.Sent("a@x.y")); got != 2 {
		t.Errorf("Sent(a) = %d, want 2", got)
	}

	fake.Err = errors.New("smtp down")
	if err := fake.Send(t.Context(), kernelmail.Message{To: "a@x.y"}); err == nil || len(fake.Sent("a@x.y")) != 2 {
		t.Error("a failing Fake recorded the message or returned nil")
	}
}

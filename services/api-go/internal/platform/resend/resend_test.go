package resend_test

import (
	"encoding/base64"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend/resendtest"
)

func newClient(t *testing.T, baseURL, key string) *resend.Client {
	t.Helper()

	client, err := resend.New(resend.Config{
		BaseURL: baseURL, APIKey: key, From: mail.Address{Name: "My Workouts", Email: "noreply@my-workouts.online"},
	})
	if err != nil {
		t.Fatal(err)
	}

	return client
}

func TestSendPostsTheMessage(t *testing.T) {
	t.Parallel()

	srv := resendtest.New(t)
	client := newClient(t, srv.URL, resendtest.APIKey)

	err := client.Send(t.Context(), mail.Message{
		To: "owner@example.com", FromName: "Jane Doe via My Workouts", Subject: "Lost workout",
		HTML: "<p>Hi</p>", Text: "Hi",
		ReplyTo:     []mail.Address{{Name: "Jane Doe", Email: "jane@example.com"}},
		Attachments: []mail.Attachment{{Filename: "a.png", ContentType: "image/png", Content: []byte("png-bytes")}},
	})
	if err != nil {
		t.Fatal(err)
	}

	sent := srv.Sent()
	if len(sent) != 1 {
		t.Fatalf("sent %d emails, want 1", len(sent))
	}

	got := sent[0]
	if got.From != "Jane Doe via My Workouts <noreply@my-workouts.online>" || got.Subject != "Lost workout" ||
		got.HTML != "<p>Hi</p>" || got.Text != "Hi" {
		t.Errorf("sent %+v", got)
	}

	if diff := cmp.Diff([]string{"owner@example.com"}, got.To); diff != "" {
		t.Errorf("to (-want +got):\n%s", diff)
	}

	if diff := cmp.Diff([]string{"Jane Doe <jane@example.com>"}, got.ReplyTo); diff != "" {
		t.Errorf("reply_to (-want +got):\n%s", diff)
	}

	if len(got.Attachments) != 1 || got.Attachments[0].Filename != "a.png" || got.Attachments[0].ContentType != "image/png" ||
		got.Attachments[0].Content != base64.StdEncoding.EncodeToString([]byte("png-bytes")) {
		t.Errorf("attachments = %+v", got.Attachments)
	}
}

func TestSendUsesTheConfiguredSenderByDefault(t *testing.T) {
	t.Parallel()

	srv := resendtest.New(t)
	if err := newClient(t, srv.URL, resendtest.APIKey).Send(t.Context(), mail.Message{To: "a@x.y", Subject: "Hi", Text: "Hi"}); err != nil {
		t.Fatal(err)
	}

	if sent := srv.Sent(); len(sent) != 1 || sent[0].From != "My Workouts <noreply@my-workouts.online>" || sent[0].ReplyTo != nil {
		t.Errorf("sent = %+v", sent)
	}
}

func TestReceivedEmailAndAttachments(t *testing.T) {
	t.Parallel()

	srv := resendtest.New(t)
	srv.AddEmail(resend.ReceivedEmail{ID: "email-1", From: "Jane <jane@example.com>", Subject: "Help", HTML: "<p>Hi</p>"},
		resendtest.File{Filename: "a.png", ContentType: "image/png", Content: []byte("png-bytes")})

	client := newClient(t, strings.TrimSuffix(srv.URL, "/"), resendtest.APIKey)

	email, err := client.ReceivedEmail(t.Context(), "email-1")
	if err != nil {
		t.Fatal(err)
	}

	if email.From != "Jane <jane@example.com>" || email.Subject != "Help" || len(email.Attachments) != 1 {
		t.Errorf("email = %+v", email)
	}

	attachments, err := client.ReceivedAttachments(t.Context(), "email-1")
	if err != nil {
		t.Fatal(err)
	}

	if len(attachments) != 1 || attachments[0].ContentType != "image/png" {
		t.Fatalf("attachments = %+v", attachments)
	}

	content, err := client.Download(t.Context(), attachments[0].DownloadURL)
	if err != nil || string(content) != "png-bytes" {
		t.Errorf("Download() = %q, %v", content, err)
	}

	if diff := cmp.Diff([]string{""}, srv.DownloadAuthorization()); diff != "" {
		t.Errorf("a download sent the API key (-want +got):\n%s", diff)
	}
}

func TestErrorsCarryTheStatus(t *testing.T) {
	t.Parallel()

	srv := resendtest.New(t)

	_, err := newClient(t, srv.URL, resendtest.APIKey).ReceivedEmail(t.Context(), "missing")
	if !resend.IsNotFound(err) {
		t.Errorf("unknown email: err = %v, want a 404", err)
	}

	srv.FailWith(http.StatusInternalServerError)

	_, err = newClient(t, srv.URL, resendtest.APIKey).ReceivedEmail(t.Context(), "missing")

	var apiErr *resend.APIError
	if !errors.As(err, &apiErr) || apiErr.Status != http.StatusInternalServerError || resend.IsNotFound(err) {
		t.Errorf("outage: err = %v, want a 500 APIError", err)
	}

	err = newClient(t, srv.URL, "wrong").Send(t.Context(), mail.Message{To: "a@x.y"})
	if !errors.As(err, &apiErr) || apiErr.Status != http.StatusUnauthorized || apiErr.Name != "missing_api_key" {
		t.Errorf("wrong key: err = %v, want the 401 with its name", err)
	}

	if _, err := newClient(t, srv.URL, resendtest.APIKey).Download(t.Context(), srv.URL+"files/none/0"); !resend.IsNotFound(err) {
		t.Errorf("missing download: err = %v, want a 404", err)
	}
}

func TestANonJSONErrorKeepsTheStatusLine(t *testing.T) {
	t.Parallel()

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		http.Error(w, "upstream down", http.StatusBadGateway)
	}))
	t.Cleanup(srv.Close)

	_, err := newClient(t, srv.URL, "key").ReceivedEmail(t.Context(), "email-1")

	var apiErr *resend.APIError
	if !errors.As(err, &apiErr) || apiErr.Status != http.StatusBadGateway || apiErr.Message != "502 Bad Gateway" {
		t.Errorf("err = %v, want a 502 APIError with the status line", err)
	}
}

func TestNewRejectsABadBaseURL(t *testing.T) {
	t.Parallel()

	if _, err := resend.New(resend.Config{BaseURL: "http://[::1"}); err == nil {
		t.Error("New() error = nil, want a base URL error")
	}

	if _, err := resend.New(resend.Config{}); err != nil {
		t.Errorf("New() with the default base URL: %v", err)
	}
}

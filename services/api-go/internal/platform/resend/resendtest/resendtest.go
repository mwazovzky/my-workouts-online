// Package resendtest is a fake Resend API for tests: the receiving API
// (emails and their attachments, with download URLs on the same server) and
// the send API, which records what was sent.
//
//	srv := resendtest.New(t)
//	srv.AddEmail(resend.ReceivedEmail{ID: "email-1", …}, resendtest.File{…})
//	client, _ := resend.New(resend.Config{BaseURL: srv.URL, APIKey: resendtest.APIKey})
package resendtest

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strconv"
	"sync"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend"
)

// APIKey is the key the server accepts; any other is a 401.
const APIKey = "re_test_key"

// File is a received attachment with its content.
type File struct {
	Filename    string
	ContentType string
	Content     []byte
}

// Sent is one POST /emails body.
type Sent struct {
	From        string   `json:"from"`
	To          []string `json:"to"`
	Subject     string   `json:"subject"`
	HTML        string   `json:"html"`
	Text        string   `json:"text"`
	ReplyTo     []string `json:"reply_to"`
	Attachments []struct {
		Filename    string `json:"filename"`
		Content     string `json:"content"`
		ContentType string `json:"content_type"`
	} `json:"attachments"`
}

// Server is the fake API. It is safe for concurrent use.
type Server struct {
	// URL is the API root to configure the client with.
	URL string

	mu          sync.Mutex
	emails      map[string]resend.ReceivedEmail
	files       map[string][]File
	failWith    int
	sent        []Sent
	downloadKey []string
}

// New starts a Server, closed when the test ends.
func New(t testing.TB) *Server {
	t.Helper()

	s := &Server{emails: map[string]resend.ReceivedEmail{}, files: map[string][]File{}}

	mux := http.NewServeMux()
	mux.HandleFunc("POST /emails", s.authorized(s.send))
	mux.HandleFunc("GET /emails/receiving/{id}", s.authorized(s.receivedEmail))
	mux.HandleFunc("GET /emails/receiving/{id}/attachments", s.authorized(s.attachments))
	mux.HandleFunc("GET /files/{id}/{index}", s.download)

	srv := httptest.NewServer(mux)
	t.Cleanup(srv.Close)

	s.URL = srv.URL + "/"

	return s
}

// AddEmail makes email readable; its attachment list is derived from files.
func (s *Server) AddEmail(email resend.ReceivedEmail, files ...File) {
	s.mu.Lock()
	defer s.mu.Unlock()

	email.Attachments = nil
	for _, file := range files {
		email.Attachments = append(email.Attachments, struct {
			ID       string `json:"id"`
			Filename string `json:"filename"`
		}{ID: "att-" + file.Filename, Filename: file.Filename})
	}

	s.emails[email.ID] = email
	s.files[email.ID] = files
}

// FailWith makes every receiving call answer status; 0 restores normal
// answers.
func (s *Server) FailWith(status int) {
	s.mu.Lock()
	defer s.mu.Unlock()

	s.failWith = status
}

// Sent returns the emails sent, oldest first.
func (s *Server) Sent() []Sent {
	s.mu.Lock()
	defer s.mu.Unlock()

	return append([]Sent(nil), s.sent...)
}

// DownloadAuthorization returns the Authorization header of every download,
// which must be empty: download URLs are pre-signed.
func (s *Server) DownloadAuthorization() []string {
	s.mu.Lock()
	defer s.mu.Unlock()

	return append([]string(nil), s.downloadKey...)
}

func (s *Server) authorized(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer "+APIKey {
			writeError(w, http.StatusUnauthorized, "missing_api_key", "Missing API key in the authorization header")

			return
		}

		next(w, r)
	}
}

func (s *Server) send(w http.ResponseWriter, r *http.Request) {
	var body Sent
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		writeError(w, http.StatusUnprocessableEntity, "validation_error", err.Error())

		return
	}

	s.mu.Lock()
	s.sent = append(s.sent, body)
	s.mu.Unlock()

	writeJSON(w, http.StatusOK, map[string]string{"id": "sent-1"})
}

func (s *Server) receivedEmail(w http.ResponseWriter, r *http.Request) {
	email, ok := s.lookup(w, r)
	if ok {
		writeJSON(w, http.StatusOK, email)
	}
}

func (s *Server) attachments(w http.ResponseWriter, r *http.Request) {
	email, ok := s.lookup(w, r)
	if !ok {
		return
	}

	s.mu.Lock()
	files := s.files[email.ID]
	s.mu.Unlock()

	data := make([]resend.ReceivedAttachment, 0, len(files))
	for i, file := range files {
		data = append(data, resend.ReceivedAttachment{
			ID: "att-" + file.Filename, Filename: file.Filename, ContentType: file.ContentType,
			DownloadURL: s.URL + "files/" + email.ID + "/" + strconv.Itoa(i),
		})
	}

	writeJSON(w, http.StatusOK, map[string]any{"object": "list", "has_more": false, "data": data})
}

func (s *Server) download(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	defer s.mu.Unlock()

	s.downloadKey = append(s.downloadKey, r.Header.Get("Authorization"))

	files := s.files[r.PathValue("id")]

	index, err := strconv.Atoi(r.PathValue("index"))
	if err != nil || index < 0 || index >= len(files) {
		http.NotFound(w, r)

		return
	}

	w.Header().Set("Content-Type", files[index].ContentType)
	_, _ = w.Write(files[index].Content)
}

func (s *Server) lookup(w http.ResponseWriter, r *http.Request) (resend.ReceivedEmail, bool) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.failWith != 0 {
		writeError(w, s.failWith, "application_error", http.StatusText(s.failWith))

		return resend.ReceivedEmail{}, false
	}

	email, ok := s.emails[r.PathValue("id")]
	if !ok {
		writeError(w, http.StatusNotFound, "not_found", "Email not found")
	}

	return email, ok
}

func writeJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}

func writeError(w http.ResponseWriter, status int, name, message string) {
	writeJSON(w, status, map[string]any{"statusCode": status, "name": name, "message": message})
}

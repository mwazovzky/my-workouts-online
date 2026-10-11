package httpapi_test

import (
	"bytes"
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/ratelimit"
)

const requestID = "550e8400-e29b-41d4-a716-446655440000"

type fixture struct {
	server *httpapi.Server
	clock  *clock.Fake
	logs   *bytes.Buffer
}

// newFixture returns a Server with test routes: /api/fail returns the error
// the test sets, /api/panic panics.
func newFixture(t *testing.T, perMinute int, fail error) *fixture {
	t.Helper()

	bundle, err := i18n.NewBundle()
	if err != nil {
		t.Fatal(err)
	}

	f := &fixture{
		clock: clock.NewFake(time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC)),
		logs:  &bytes.Buffer{},
	}

	f.server, err = httpapi.NewServer(httpapi.Options{
		AppURL:             "https://my-workouts.online/",
		RateLimitPerMinute: perMinute,
		Bundle:             bundle,
		Logger:             slog.New(slog.NewJSONHandler(f.logs, nil)),
		Clock:              f.clock,
		Limiter:            ratelimit.NewMemory(f.clock),
	})
	if err != nil {
		t.Fatal(err)
	}

	f.server.Handle("GET /api/fail", http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		f.server.Responder().Error(w, r, fail)
	}))
	f.server.Handle("GET /api/panic", http.HandlerFunc(func(http.ResponseWriter, *http.Request) {
		panic("boom")
	}))
	f.server.Handle("GET /api/ok", http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		f.server.Responder().JSON(w, http.StatusOK, map[string]string{"ok": "yes"})
	}))

	return f
}

func (f *fixture) do(t *testing.T, path string, header map[string]string) *httptest.ResponseRecorder {
	t.Helper()

	r := httptest.NewRequestWithContext(t.Context(), http.MethodGet, path, http.NoBody)
	for k, v := range header {
		r.Header.Set(k, v)
	}

	rec := httptest.NewRecorder()
	f.server.Handler().ServeHTTP(rec, r)

	return rec
}

func problemOf(t *testing.T, rec *httptest.ResponseRecorder) gen.ValidationProblem {
	t.Helper()

	if got := rec.Header().Get("Content-Type"); got != httpapi.ProblemContentType {
		t.Errorf("Content-Type = %q, want %q", got, httpapi.ProblemContentType)
	}

	var p gen.ValidationProblem
	if err := json.Unmarshal(rec.Body.Bytes(), &p); err != nil {
		t.Fatalf("body is not a Problem: %v\n%s", err, rec.Body)
	}

	return p
}

func TestErrorMapping(t *testing.T) {
	t.Parallel()

	field := apperr.FieldError{Pointer: "/name", Code: apperr.FieldRequired, Detail: "validation.required", Args: map[string]string{"attribute": "name"}}

	tests := []struct {
		name       string
		err        error
		wantStatus int
		wantCode   string
		wantTitle  string
		wantDetail string
	}{
		{"not found", apperr.NotFound(nil), 404, "not_found", "Not found", "Запись не найдена."},
		{"in use", apperr.Conflict(apperr.CodeInUse, "Cannot delete equipment that is used by exercises.", nil), 409, "in_use", "Resource in use", "Нельзя удалить оборудование, используемое упражнениями."},
		{"validation", apperr.Validation(field), 422, "validation_failed", "Validation failed", "Поле name обязательно."},
		{"unauthenticated", apperr.Unauthenticated(apperr.CodeTokenExpired, "Unauthenticated."), 401, "token_expired", "Access token expired", "Требуется вход."},
		{"forbidden", apperr.Forbidden(apperr.CodeForbidden, "This action is unauthorized."), 403, "forbidden", "Forbidden", "Действие не разрешено."},
		{"rate limited", apperr.RateLimited(apperr.CodeTooManyAttempts, 1500*time.Millisecond), 429, "too_many_attempts", "Too many attempts", "Слишком много попыток."},
		{"precondition failed", apperr.PreconditionFailed("This workout was changed elsewhere. Reload it to continue."), 412, "precondition_failed", "Precondition failed", "Эта тренировка была изменена в другом месте. Обновите её, чтобы продолжить."},
		{"precondition required", apperr.PreconditionRequired("x"), 428, "precondition_required", "Precondition required", "x"},
		{"bad request", apperr.BadRequest("Bad request", nil), 400, "bad_request", "Bad request", "Bad request"},
		{"not implemented", httpapi.ErrNotImplemented, 501, "internal_error", "Internal server error", "Эта операция ещё не реализована."},
		{"unexpected", errors.New("pq: connection reset"), 500, "internal_error", "Internal server error", "Ошибка сервера"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			f := newFixture(t, 1000, tt.err)
			rec := f.do(t, "/api/fail", map[string]string{"Accept-Language": "ru", "X-Request-Id": requestID})

			if rec.Code != tt.wantStatus {
				t.Errorf("status = %d, want %d", rec.Code, tt.wantStatus)
			}

			p := problemOf(t, rec)
			if string(p.Code) != tt.wantCode || p.Title != tt.wantTitle || p.Detail != tt.wantDetail || int(p.Status) != tt.wantStatus {
				t.Errorf("problem = %+v, want code %q, title %q, detail %q", p, tt.wantCode, tt.wantTitle, tt.wantDetail)
			}

			if p.Type != "https://my-workouts.online/problems/"+tt.wantCode {
				t.Errorf("type = %q", p.Type)
			}

			if p.Instance == nil || *p.Instance != "/api/fail#"+requestID {
				t.Errorf("instance = %v, want the path and the request id", p.Instance)
			}

			if strings.Contains(rec.Body.String(), "pq:") {
				t.Errorf("the body leaks the cause: %s", rec.Body)
			}
		})
	}
}

func TestRateLimitedProblemHasRetryAfterRoundedUp(t *testing.T) {
	t.Parallel()

	rec := newFixture(t, 1000, apperr.RateLimited(apperr.CodeTooManyRequests, 1500*time.Millisecond)).do(t, "/api/fail", nil)

	if got := rec.Header().Get("Retry-After"); got != "2" {
		t.Errorf("Retry-After = %q, want 2", got)
	}
}

func TestValidationDetailSummarisesTheRest(t *testing.T) {
	t.Parallel()

	fields := []apperr.FieldError{
		{Pointer: "/a", Code: apperr.FieldRequired, Detail: "validation.required", Args: map[string]string{"attribute": "a"}},
		{Pointer: "/b", Code: apperr.FieldRequired, Detail: "validation.required", Args: map[string]string{"attribute": "b"}},
		{Pointer: "/c", Code: apperr.FieldRequired, Detail: "validation.required", Args: map[string]string{"attribute": "c"}},
	}

	for count, want := range map[int]string{
		1: "The a field is required.",
		2: "The a field is required. (and 1 more error)",
		3: "The a field is required. (and 2 more errors)",
	} {
		p := problemOf(t, newFixture(t, 1000, apperr.Validation(fields[:count]...)).do(t, "/api/fail", nil))
		if p.Detail != want {
			t.Errorf("%d errors: detail = %q, want %q", count, p.Detail, want)
		}
	}
}

// Every code the backend can emit is in the contract's ErrorCode enum and
// has a title, and the field codes match FieldErrorCode.
func TestCodesMatchTheContract(t *testing.T) {
	t.Parallel()

	spec, err := gen.GetSpec()
	if err != nil {
		t.Fatal(err)
	}

	enum := func(schema string) []string {
		var values []string
		for _, v := range spec.Components.Schemas[schema].Value.Enum {
			values = append(values, v.(string))
		}

		slices.Sort(values)

		return values
	}

	var codes, fieldCodes []string
	for _, c := range apperr.Codes() {
		codes = append(codes, string(c))

		p := problemOf(t, newFixture(t, 1000, &apperr.Error{Kind: apperr.KindConflict, Code: c, Detail: "x"}).do(t, "/api/fail", nil))
		if p.Title == "" {
			t.Errorf("code %q has no title", c)
		}
	}

	for _, c := range apperr.FieldCodes() {
		fieldCodes = append(fieldCodes, string(c))
	}

	slices.Sort(codes)
	slices.Sort(fieldCodes)

	if diff := cmp.Diff(enum("ErrorCode"), codes); diff != "" {
		t.Errorf("apperr codes differ from the contract's ErrorCode (-contract +apperr):\n%s", diff)
	}

	if diff := cmp.Diff(enum("FieldErrorCode"), fieldCodes); diff != "" {
		t.Errorf("apperr field codes differ from the contract's FieldErrorCode (-contract +apperr):\n%s", diff)
	}
}

func TestRequestID(t *testing.T) {
	t.Parallel()

	f := newFixture(t, 1000, nil)

	if got := f.do(t, "/api/ok", map[string]string{"X-Request-Id": requestID}).Header().Get("X-Request-Id"); got != requestID {
		t.Errorf("a valid id is replaced: got %q", got)
	}

	for _, supplied := range []string{"", "not-a-uuid-" + strings.Repeat("x", 200), "{" + requestID + "}"} {
		got := f.do(t, "/api/ok", map[string]string{"X-Request-Id": supplied}).Header().Get("X-Request-Id")
		if got == supplied || len(got) != 36 {
			t.Errorf("supplied %q: got %q, want a fresh UUID", supplied, got)
		}
	}
}

func TestPanicBecomesAProblemAndIsLogged(t *testing.T) {
	t.Parallel()

	f := newFixture(t, 1000, nil)
	rec := f.do(t, "/api/panic", map[string]string{"X-Request-Id": requestID})

	if rec.Code != http.StatusInternalServerError {
		t.Errorf("status = %d, want 500", rec.Code)
	}

	if p := problemOf(t, rec); p.Code != "internal_error" || p.Detail != "Server Error" {
		t.Errorf("problem = %+v", p)
	}

	if strings.Contains(rec.Body.String(), "boom") || strings.Contains(rec.Body.String(), "goroutine") {
		t.Errorf("the body leaks the panic: %s", rec.Body)
	}

	panicLog := logLine(t, f.logs, "panic")
	if panicLog["request_id"] != requestID || !strings.Contains(panicLog["stack"].(string), "goroutine") {
		t.Errorf("panic log = %v, want the request id and the stack", panicLog)
	}
}

func TestAccessLog(t *testing.T) {
	t.Parallel()

	f := newFixture(t, 1000, nil)
	f.do(t, "/api/ok", map[string]string{"X-Request-Id": requestID})

	line := logLine(t, f.logs, "request")

	want := map[string]any{"request_id": requestID, "method": "GET", "path": "/api/ok", "route": "GET /api/ok", "status": float64(200)}
	for key, value := range want {
		if line[key] != value {
			t.Errorf("log %s = %v, want %v", key, line[key], value)
		}
	}
}

func TestRateLimit(t *testing.T) {
	t.Parallel()

	f := newFixture(t, 2, nil)

	for i, want := range []string{"1", "0"} {
		rec := f.do(t, "/api/ok", nil)
		if rec.Code != http.StatusOK || rec.Header().Get("X-RateLimit-Remaining") != want || rec.Header().Get("X-RateLimit-Limit") != "2" {
			t.Errorf("request %d: %d, remaining %q", i+1, rec.Code, rec.Header().Get("X-RateLimit-Remaining"))
		}
	}

	f.clock.Advance(15 * time.Second)

	rec := f.do(t, "/api/ok", nil)
	if rec.Code != http.StatusTooManyRequests || rec.Header().Get("Retry-After") != "45" {
		t.Errorf("over the limit: %d, Retry-After %q; want 429 and 45", rec.Code, rec.Header().Get("Retry-After"))
	}

	if p := problemOf(t, rec); p.Code != "too_many_requests" {
		t.Errorf("code = %q, want too_many_requests", p.Code)
	}

	// Paths outside /api/ are never limited.
	if rec := f.do(t, "/health", nil); rec.Header().Get("X-RateLimit-Limit") != "" {
		t.Error("a non-API path was rate limited")
	}

	f.clock.Advance(45 * time.Second)

	if rec := f.do(t, "/api/ok", nil); rec.Code != http.StatusOK {
		t.Errorf("after the window: %d, want 200", rec.Code)
	}
}

// logLine returns the first JSON log line with msg.
func logLine(t *testing.T, logs *bytes.Buffer, msg string) map[string]any {
	t.Helper()

	for _, raw := range bytes.Split(logs.Bytes(), []byte("\n")) {
		var line map[string]any
		if json.Unmarshal(raw, &line) == nil && line["msg"] == msg {
			return line
		}
	}

	t.Fatalf("no %q log line in:\n%s", msg, logs)

	return nil
}

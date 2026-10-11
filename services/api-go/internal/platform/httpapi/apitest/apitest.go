// Package apitest is the HTTP test style every module uses: requests go
// through the real handler from bootstrap.New with httptest, every response
// to a contract operation is validated against contracts/openapi.yaml, and
// bodies are compared with golden JSON files.
//
//	api := apitest.New(t, app.Handler)
//	res := api.Do(apitest.Request{Method: "GET", Path: "/api/v1/meta", Locale: "ru"})
//	res.AssertStatus(http.StatusOK)
//	res.AssertGolden("meta.golden.json")
//
// Golden files live in the calling package's testdata/; `go test ./… -update`
// rewrites them (review the diff). Bodies are compared as JSON values, so a
// golden file may be copied verbatim from the PHP API's response.
package apitest

import (
	"bytes"
	"encoding/json"
	"flag"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/getkin/kin-openapi/openapi3filter"
	"github.com/getkin/kin-openapi/routers"
	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

var update = flag.Bool("update", false, "rewrite the golden files in testdata")

// RequestID is the X-Request-Id every request sends unless it sets its own,
// so a Problem's instance is stable in golden files.
const RequestID = "00000000-0000-4000-8000-000000000001"

// Client sends requests to a handler and checks the responses.
type Client struct {
	t       testing.TB
	handler http.Handler
	// routes resolves a request to its contract operation through a ServeMux
	// holding the contract's patterns, as the real server does.
	routes   *http.ServeMux
	byRoute  map[string]*routers.Route
	validate *openapi3filter.Options
}

// New returns a Client for handler.
func New(t testing.TB, handler http.Handler) *Client {
	t.Helper()

	spec, err := gen.GetSpec()
	if err != nil {
		t.Fatalf("apitest: load the contract: %v", err)
	}

	c := &Client{
		t:        t,
		handler:  handler,
		routes:   http.NewServeMux(),
		byRoute:  map[string]*routers.Route{},
		validate: &openapi3filter.Options{IncludeResponseStatus: true},
	}

	for path, item := range spec.Paths.Map() {
		for method, operation := range item.Operations() {
			pattern := method + " " + path
			c.byRoute[pattern] = &routers.Route{Spec: spec, Path: path, PathItem: item, Method: method, Operation: operation}
			c.routes.Handle(pattern, http.NotFoundHandler())
		}
	}

	return c
}

// Request describes one request. Body is sent as is when it is a string,
// and JSON-encoded otherwise.
type Request struct {
	Method string
	Path   string
	Body   any
	// Locale sets Accept-Language.
	Locale string
	// Token sets a bearer Authorization header.
	Token  string
	Header http.Header
	// SkipContract skips response validation, for responses the contract
	// does not describe (an unimplemented operation's 501).
	SkipContract bool
}

// Response is a recorded response.
type Response struct {
	*httptest.ResponseRecorder
	t testing.TB
}

// Do sends req and, for a contract operation, fails the test when the
// response does not match the contract.
func (c *Client) Do(req Request) *Response {
	c.t.Helper()

	var body io.Reader = http.NoBody

	switch b := req.Body.(type) {
	case nil:
	case string:
		body = strings.NewReader(b)
	default:
		data, err := json.Marshal(b)
		if err != nil {
			c.t.Fatalf("apitest: encode body: %v", err)
		}

		body = bytes.NewReader(data)
	}

	r := httptest.NewRequestWithContext(c.t.Context(), req.Method, req.Path, body)
	r.Header.Set("Accept", "application/json")
	r.Header.Set("X-Request-Id", RequestID)

	if req.Body != nil {
		r.Header.Set("Content-Type", "application/json")
	}

	if req.Locale != "" {
		r.Header.Set("Accept-Language", req.Locale)
	}

	if req.Token != "" {
		r.Header.Set("Authorization", "Bearer "+req.Token)
	}

	for name, values := range req.Header {
		r.Header[name] = values
	}

	rec := httptest.NewRecorder()
	c.handler.ServeHTTP(rec, r)

	if !req.SkipContract {
		c.checkContract(r, rec)
	}

	return &Response{ResponseRecorder: rec, t: c.t}
}

func (c *Client) checkContract(r *http.Request, rec *httptest.ResponseRecorder) {
	c.t.Helper()

	_, pattern := c.routes.Handler(r)

	route, ok := c.byRoute[pattern]
	if !ok {
		return
	}

	err := openapi3filter.ValidateResponse(r.Context(), &openapi3filter.ResponseValidationInput{
		RequestValidationInput: &openapi3filter.RequestValidationInput{Request: r, Route: route, Options: c.validate},
		Status:                 rec.Code,
		Header:                 rec.Header(),
		Body:                   io.NopCloser(bytes.NewReader(rec.Body.Bytes())),
		Options:                c.validate,
	})
	if err != nil {
		c.t.Errorf("apitest: %s %s: response %d does not match the contract: %v\nbody: %s",
			r.Method, r.URL.Path, rec.Code, err, rec.Body.String())
	}
}

// AssertStatus fails the test when the status is not want.
func (r *Response) AssertStatus(want int) *Response {
	r.t.Helper()

	if r.Code != want {
		r.t.Errorf("status = %d, want %d; body: %s", r.Code, want, r.Body.String())
	}

	return r
}

// AssertHeader fails the test when header name is not want.
func (r *Response) AssertHeader(name, want string) *Response {
	r.t.Helper()

	if got := r.Header().Get(name); got != want {
		r.t.Errorf("%s = %q, want %q", name, got, want)
	}

	return r
}

// AssertGolden compares the body with testdata/name as JSON values. With
// -update it writes the body there first, indented.
func (r *Response) AssertGolden(name string) *Response {
	r.t.Helper()

	path := filepath.Join("testdata", name)

	if *update {
		var pretty bytes.Buffer
		if err := json.Indent(&pretty, r.Body.Bytes(), "", "  "); err != nil {
			r.t.Fatalf("apitest: body is not JSON: %v", err)
		}

		pretty.WriteByte('\n')

		if err := os.MkdirAll("testdata", 0o750); err != nil {
			r.t.Fatal(err)
		}

		if err := os.WriteFile(path, pretty.Bytes(), 0o600); err != nil {
			r.t.Fatal(err)
		}
	}

	want, err := os.ReadFile(path) //nolint:gosec // G304: a golden file the test names, under testdata
	if err != nil {
		r.t.Fatalf("apitest: read golden file: %v", err)
	}

	if diff := cmp.Diff(decode(r.t, want), decode(r.t, r.Body.Bytes())); diff != "" {
		r.t.Errorf("body mismatch with %s (-want +got):\n%s", path, diff)
	}

	return r
}

// Problem decodes the body as a Problem, validation errors included.
func (r *Response) Problem() gen.ValidationProblem {
	r.t.Helper()

	if got := r.Header().Get("Content-Type"); got != "application/problem+json" {
		r.t.Errorf("Content-Type = %q, want application/problem+json", got)
	}

	var p gen.ValidationProblem
	if err := json.Unmarshal(r.Body.Bytes(), &p); err != nil {
		r.t.Fatalf("apitest: body is not a Problem: %v", err)
	}

	return p
}

func decode(t testing.TB, data []byte) any {
	t.Helper()

	var v any
	if err := json.Unmarshal(data, &v); err != nil {
		t.Fatalf("apitest: not JSON: %v\n%s", err, data)
	}

	return v
}

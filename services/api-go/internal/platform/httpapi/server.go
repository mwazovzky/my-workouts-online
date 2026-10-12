package httpapi

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"net/http"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/ratelimit"
)

// Options configure the HTTP platform.
type Options struct {
	// AppURL is the public base URL; Problem types live under it.
	AppURL string
	// RateLimitPerMinute is the per-client limit under /api/.
	RateLimitPerMinute int
	Bundle             *i18n.Bundle
	Logger             *slog.Logger
	Clock              clock.Clock
	Limiter            ratelimit.Limiter
	// Authenticator resolves bearer tokens for every non-public operation.
	Authenticator Authenticator
	// Throttles are per-operation limits, keyed by operationId.
	Throttles map[string]Throttle
	// FieldMessages customise request validation messages, keyed by
	// operationId.
	FieldMessages map[string]FieldMessages
}

// Server is the routing table plus the middleware chain around it.
type Server struct {
	opts       Options
	mux        *http.ServeMux
	responder  *Responder
	validator  *requestValidator
	operations map[string]operation
}

// NewServer loads the embedded contract and returns a Server with no routes.
func NewServer(opts Options) (*Server, error) {
	spec, err := gen.GetSpec()
	if err != nil {
		return nil, fmt.Errorf("httpapi: load the contract: %w", err)
	}

	responder := NewResponder(opts.AppURL, opts.Bundle)

	return &Server{
		opts:       opts,
		mux:        http.NewServeMux(),
		responder:  responder,
		validator:  newRequestValidator(spec, responder, opts.FieldMessages),
		operations: operationsOf(spec),
	}, nil
}

// Responder returns the responder handlers write errors through.
func (s *Server) Responder() *Responder { return s.responder }

// Handle registers an off-contract route, such as the health probes.
func (s *Server) Handle(pattern string, handler http.Handler) {
	s.mux.Handle(pattern, handler)
}

// Mount registers every contract operation, served by api. Each request is
// validated against the contract before api sees it.
func (s *Server) Mount(api gen.StrictServerInterface) {
	strict := gen.NewStrictHandlerWithOptions(api, nil, gen.StrictHTTPServerOptions{
		// Reached only if a body passed validation yet fails to decode.
		RequestErrorHandlerFunc: func(w http.ResponseWriter, r *http.Request, _ error) {
			s.responder.Error(w, r, apperr.Validation(apperr.FieldError{
				Pointer: "", Code: apperr.FieldInvalidFormat, Detail: "The request body must be valid JSON.",
			}))
		},
		ResponseErrorHandlerFunc: s.responder.Error,
	})

	gen.HandlerWithOptions(strict, gen.StdHTTPServerOptions{
		BaseRouter: s.mux,
		// The last middleware is the outermost: authorize, then throttle (keyed
		// by the principal), then validate the request.
		Middlewares:      []gen.MiddlewareFunc{s.validator.middleware, s.throttle, s.authorize},
		ErrorHandlerFunc: s.paramError,
	})
}

// Handler returns the routes wrapped in the middleware chain (see the
// package comment).
func (s *Server) Handler() http.Handler {
	var h http.Handler = http.HandlerFunc(s.route)
	h = rateLimit(s.opts.Limiter, s.opts.RateLimitPerMinute, s.responder)(h)
	h = negotiateLocale(s.opts.Bundle)(h)
	h = recoverPanics(s.responder)(h)
	h = accessLog(s.opts.Logger, s.opts.Clock)(h)

	return requestID(h)
}

// route dispatches through the mux, answering an unmatched path with a 404
// Problem and a wrong method with a 405 Problem and Allow, where the mux
// would write plain text.
func (s *Server) route(w http.ResponseWriter, r *http.Request) {
	handler, pattern := s.mux.Handler(r)

	if matched := routeFrom(r.Context()); matched != nil {
		matched.pattern = pattern
	}

	if pattern != "" {
		s.mux.ServeHTTP(w, r.WithContext(context.WithValue(r.Context(), requestKey, r)))

		return
	}

	// Let the mux decide between 404 and 405 (and compute Allow) without
	// writing its plain-text body.
	probe := &headerProbe{header: http.Header{}}
	handler.ServeHTTP(probe, r)

	if probe.status == http.StatusMethodNotAllowed {
		allow := probe.header.Get("Allow")
		w.Header().Set("Allow", allow)
		s.responder.Problem(w, r, http.StatusMethodNotAllowed, apperr.CodeMethodNotAllowed,
			"The :method method is not supported for this route. Supported methods: :allow.",
			map[string]string{"method": r.Method, "allow": allow})

		return
	}

	s.responder.Error(w, r, apperr.NotFound(nil))
}

// headerProbe is a ResponseWriter that keeps the header and status and
// discards the body.
type headerProbe struct {
	header http.Header
	status int
}

func (p *headerProbe) Header() http.Header         { return p.header }
func (p *headerProbe) Write(b []byte) (int, error) { return len(b), nil }
func (p *headerProbe) WriteHeader(status int)      { p.status = status }

// paramError answers a path or query parameter the generated server could
// not bind. A malformed path parameter is a 404, as in the PHP API, where
// every id route is constrained to digits; a query parameter is a 422.
func (s *Server) paramError(w http.ResponseWriter, r *http.Request, err error) {
	name := paramName(err)

	if name != "" && r.PathValue(name) != "" {
		s.responder.Error(w, r, apperr.NotFound(err))

		return
	}

	code, detail := apperr.FieldInvalidFormat, "validation.regex"

	var (
		required       *gen.RequiredParamError
		requiredHeader *gen.RequiredHeaderError
	)

	if errors.As(err, &required) || errors.As(err, &requiredHeader) {
		code, detail = apperr.FieldRequired, "validation.required"
	}

	s.responder.Error(w, r, apperr.Validation(fieldError("/"+name, code, detail, nil)))
}

func paramName(err error) string {
	var (
		format    *gen.InvalidParamFormatError
		required  *gen.RequiredParamError
		unmarshal *gen.UnmarshalingParamError
		tooMany   *gen.TooManyValuesForParamError
		header    *gen.RequiredHeaderError
	)

	switch {
	case errors.As(err, &format):
		return format.ParamName
	case errors.As(err, &required):
		return required.ParamName
	case errors.As(err, &unmarshal):
		return unmarshal.ParamName
	case errors.As(err, &tooMany):
		return tooMany.ParamName
	case errors.As(err, &header):
		return header.ParamName
	default:
		return ""
	}
}

package httpapi

import (
	"context"
	"net/http"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/getkin/kin-openapi/openapi3"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
)

// Authenticator resolves a bearer access token to the caller. The identity
// module implements it; errors are apperr Unauthenticated values
// (token_expired or unauthenticated).
type Authenticator interface {
	Authenticate(ctx context.Context, bearer string) (auth.Principal, error)
}

// Throttle limits one operation to Limit requests per Window, per user when
// signed in and per client IP otherwise.
type Throttle struct {
	Limit  int
	Window time.Duration
}

// operation is the policy the contract declares for one operation.
type operation struct {
	id string
	// public: `security: []`; everything else needs a bearer token.
	public bool
	// admin: tagged Admin; the caller must be an admin.
	admin bool
	// verified: needs a verified email. Every signed-in operation except
	// those tagged Auth or Me, the same set the PHP API wraps in its
	// `verified` middleware: the account and session operations stay
	// reachable so an unverified user can verify, edit or sign out.
	verified bool
}

// operationsOf indexes the contract's operations by ServeMux pattern.
func operationsOf(spec *openapi3.T) map[string]operation {
	ops := map[string]operation{}

	for path, item := range spec.Paths.Map() {
		for method, op := range item.Operations() {
			public := op.Security != nil && len(*op.Security) == 0
			ops[method+" "+path] = operation{
				id:       contractID(op.OperationID),
				public:   public,
				admin:    slices.Contains(op.Tags, "Admin"),
				verified: !public && !slices.Contains(op.Tags, "Auth") && !slices.Contains(op.Tags, "Me"),
			}
		}
	}

	return ops
}

// contractID undoes oapi-codegen's capitalisation of operationIds in the
// spec it embeds (Register for register): every operationId in the contract
// is lowerCamelCase.
func contractID(id string) string {
	if id == "" {
		return id
	}

	return strings.ToLower(id[:1]) + id[1:]
}

// authorize enforces each operation's security: public operations pass,
// others need a valid bearer token (401 unauthenticated or token_expired),
// most need a verified email (403 email_unverified), and Admin operations
// an admin (403 forbidden), checked in that order as in the PHP API. The principal goes into the
// context for handlers (auth.FromContext).
func (s *Server) authorize(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		op, ok := s.operations[r.Pattern]
		if !ok || op.public {
			next.ServeHTTP(w, r)

			return
		}

		token, found := strings.CutPrefix(r.Header.Get("Authorization"), "Bearer ")
		if !found || token == "" || s.opts.Authenticator == nil {
			s.responder.Error(w, r, apperr.Unauthenticated(apperr.CodeUnauthenticated, "Unauthenticated."))

			return
		}

		principal, err := s.opts.Authenticator.Authenticate(r.Context(), token)
		if err != nil {
			s.responder.Error(w, r, err)

			return
		}

		if info := routeFrom(r.Context()); info != nil {
			info.userID = principal.UserID
		}

		if op.verified && !principal.EmailVerified {
			s.responder.Error(w, r, apperr.Forbidden(apperr.CodeEmailUnverified, "Your email address is not verified."))

			return
		}

		if op.admin && !principal.IsAdmin {
			s.responder.Error(w, r, apperr.Forbidden(apperr.CodeForbidden, "This action is unauthorized."))

			return
		}

		next.ServeHTTP(w, r.WithContext(auth.WithPrincipal(r.Context(), principal)))
	})
}

// throttle applies the operation's Throttle, if any: 429 too_many_requests
// with Retry-After over the limit.
func (s *Server) throttle(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		op := s.operations[r.Pattern]

		limit, ok := s.opts.Throttles[op.id]
		if !ok {
			next.ServeHTTP(w, r)

			return
		}

		who := "ip:" + ClientIP(r)
		if p, signedIn := auth.FromContext(r.Context()); signedIn {
			who = "user:" + strconv.FormatInt(p.UserID, 10)
		}

		decision, err := s.opts.Limiter.Allow(r.Context(), "throttle:"+op.id+":"+who, limit.Limit, limit.Window)
		if err != nil {
			Logger(r.Context()).WarnContext(r.Context(), "throttle failed", "error", err.Error())
		} else if !decision.Allowed {
			s.responder.Error(w, r, apperr.RateLimited(apperr.CodeTooManyRequests, decision.RetryAfter))

			return
		}

		next.ServeHTTP(w, r)
	})
}

// Package auth defines the authenticated caller. The HTTP bearer middleware
// (story 4) puts a Principal in the request context; use cases read it from
// their input, handlers from the context.
package auth

import (
	"context"
	"time"
)

// Principal is the caller an access token identifies.
type Principal struct {
	UserID  int64
	IsAdmin bool
	// AuthTime is when the caller last entered their password (the auth_time
	// claim); step-up operations require it to be recent.
	AuthTime time.Time
	// Family is the refresh-token family the access token was issued from.
	Family string
}

type principalKey struct{}

// WithPrincipal returns ctx carrying p.
func WithPrincipal(ctx context.Context, p Principal) context.Context {
	return context.WithValue(ctx, principalKey{}, p)
}

// FromContext returns the Principal in ctx, if any.
func FromContext(ctx context.Context) (Principal, bool) {
	p, ok := ctx.Value(principalKey{}).(Principal)

	return p, ok
}

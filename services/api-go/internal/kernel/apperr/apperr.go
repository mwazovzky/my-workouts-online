// Package apperr defines the application's typed errors. Adapters translate
// infrastructure failures into them, use cases return them, and the HTTP
// boundary maps each Kind to a status code and a Problem Details body. Match
// them with HasKind or errors.As, never by message.
//
// Detail and FieldError.Detail are English message keys with :placeholders,
// translated per request by the HTTP boundary; Args fill the placeholders.
package apperr

import (
	"errors"
	"time"
)

// Kind classifies an Error; the HTTP boundary maps it to a status code.
type Kind uint8

const (
	// KindNotFound means the record does not exist or the caller may not see it (404).
	KindNotFound Kind = iota + 1
	// KindConflict means the change contradicts the current state, such as a
	// duplicate key or a row other rows still reference (409).
	KindConflict
	// KindValidation means the input is invalid; Fields says where (422).
	KindValidation
	// KindUnauthenticated means the caller is not, or no longer, signed in (401).
	KindUnauthenticated
	// KindForbidden means the caller is signed in but may not do this (403).
	KindForbidden
	// KindRateLimited means the caller must wait RetryAfter (429).
	KindRateLimited
	// KindPreconditionFailed means If-Match did not match the current version (412).
	KindPreconditionFailed
	// KindPreconditionRequired means a required If-Match header is missing (428).
	KindPreconditionRequired
	// KindBadRequest means the request cannot be understood at all (400).
	KindBadRequest
)

// String returns the kind's name, for logs.
func (k Kind) String() string {
	switch k {
	case KindNotFound:
		return "not_found"
	case KindConflict:
		return "conflict"
	case KindValidation:
		return "validation"
	case KindUnauthenticated:
		return "unauthenticated"
	case KindForbidden:
		return "forbidden"
	case KindRateLimited:
		return "rate_limited"
	case KindPreconditionFailed:
		return "precondition_failed"
	case KindPreconditionRequired:
		return "precondition_required"
	case KindBadRequest:
		return "bad_request"
	default:
		return "unknown"
	}
}

// Error is an application error of a known Kind.
type Error struct {
	Kind Kind
	// Code is the contract's ErrorCode for this occurrence.
	Code Code
	// Detail is the English message key; see the package comment.
	Detail string
	// Args fill Detail's :placeholders.
	Args map[string]string
	// Fields lists the rejected fields of a KindValidation error.
	Fields []FieldError
	// RetryAfter is how long a KindRateLimited caller must wait.
	RetryAfter time.Duration
	// Err is the underlying cause, if any; it is logged, never sent.
	Err error
}

// Error returns the detail followed by the cause.
func (e *Error) Error() string {
	msg := e.Detail
	if e.Kind == KindValidation && len(e.Fields) > 0 {
		msg = e.Fields[0].Pointer + ": " + e.Fields[0].Detail
	}

	if e.Err == nil {
		return msg
	}

	return msg + ": " + e.Err.Error()
}

// Unwrap returns the cause.
func (e *Error) Unwrap() error { return e.Err }

// NotFound returns the 404 error. Its detail is always "Record not found.",
// whether the row is missing or belongs to someone else.
func NotFound(cause error) error {
	return &Error{Kind: KindNotFound, Code: CodeNotFound, Detail: "Record not found.", Err: cause}
}

// Conflict returns a 409 error with code (CodeConflict, or CodeInUse for a
// reference guard).
func Conflict(code Code, detail string, cause error) error {
	return &Error{Kind: KindConflict, Code: code, Detail: detail, Err: cause}
}

// Validation returns a 422 error listing the rejected fields. With no
// fields it returns nil, so a validator can return Validation(errs...)
// unconditionally.
func Validation(fields ...FieldError) error {
	if len(fields) == 0 {
		return nil
	}

	return &Error{Kind: KindValidation, Code: CodeValidationFailed, Fields: fields}
}

// Unauthenticated returns a 401 error with code (CodeUnauthenticated,
// CodeTokenExpired, CodeInvalidCredentials, a refresh-token code, …).
func Unauthenticated(code Code, detail string) error {
	return &Error{Kind: KindUnauthenticated, Code: code, Detail: detail}
}

// Forbidden returns a 403 error with code (CodeForbidden or
// CodeEmailUnverified).
func Forbidden(code Code, detail string) error {
	return &Error{Kind: KindForbidden, Code: code, Detail: detail}
}

// RateLimited returns a 429 error with code (CodeTooManyRequests for a
// throttle, CodeTooManyAttempts for the login lockout).
func RateLimited(code Code, retryAfter time.Duration) error {
	return &Error{Kind: KindRateLimited, Code: code, Detail: "Too many attempts.", RetryAfter: retryAfter}
}

// PreconditionFailed returns the 412 error for a stale If-Match.
func PreconditionFailed(detail string) error {
	return &Error{Kind: KindPreconditionFailed, Code: CodePreconditionFailed, Detail: detail}
}

// PreconditionRequired returns the 428 error for a missing If-Match.
func PreconditionRequired(detail string) error {
	return &Error{Kind: KindPreconditionRequired, Code: CodePreconditionRequired, Detail: detail}
}

// BadRequest returns a 400 error.
func BadRequest(detail string, cause error) error {
	return &Error{Kind: KindBadRequest, Code: CodeBadRequest, Detail: detail, Err: cause}
}

// HasKind reports whether err is, or wraps, an Error of kind.
func HasKind(err error, kind Kind) bool {
	appErr, ok := As(err)

	return ok && appErr.Kind == kind
}

// As returns the Error in err's chain.
func As(err error) (*Error, bool) {
	var appErr *Error
	ok := errors.As(err, &appErr)

	return appErr, ok
}

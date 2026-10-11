// Package apperr defines the application's typed errors. Adapters translate
// infrastructure failures into them, use cases return them, and the HTTP
// boundary maps each Kind to a status code. Match them with HasKind or
// errors.As, never by message.
package apperr

import "errors"

// Kind classifies an Error.
type Kind uint8

const (
	// KindNotFound means the record does not exist or the caller may not see it.
	KindNotFound Kind = iota + 1
	// KindConflict means the change contradicts the current state, such as a
	// duplicate key or a row other rows still reference.
	KindConflict
)

// String returns the kind's name, for logs.
func (k Kind) String() string {
	switch k {
	case KindNotFound:
		return "not_found"
	case KindConflict:
		return "conflict"
	default:
		return "unknown"
	}
}

// Error is an application error of a known Kind.
type Error struct {
	Kind Kind
	// Detail says what failed, for logs and the problem detail.
	Detail string
	// Err is the underlying cause, if any.
	Err error
}

// Error returns the detail followed by the cause.
func (e *Error) Error() string {
	if e.Err == nil {
		return e.Detail
	}

	return e.Detail + ": " + e.Err.Error()
}

// Unwrap returns the cause.
func (e *Error) Unwrap() error { return e.Err }

// NotFound returns a KindNotFound error.
func NotFound(detail string, cause error) error {
	return &Error{Kind: KindNotFound, Detail: detail, Err: cause}
}

// Conflict returns a KindConflict error.
func Conflict(detail string, cause error) error {
	return &Error{Kind: KindConflict, Detail: detail, Err: cause}
}

// HasKind reports whether err is, or wraps, an Error of kind.
func HasKind(err error, kind Kind) bool {
	var appErr *Error

	return errors.As(err, &appErr) && appErr.Kind == kind
}

package apperr_test

import (
	"errors"
	"fmt"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
)

func TestConstructors(t *testing.T) {
	t.Parallel()

	cause := errors.New("duplicate key")
	field := apperr.FieldError{Pointer: "/email", Code: apperr.FieldRequired, Detail: "validation.required"}

	tests := []struct {
		name string
		err  error
		kind apperr.Kind
		code apperr.Code
	}{
		{"not found", apperr.NotFound(cause), apperr.KindNotFound, apperr.CodeNotFound},
		{"conflict", apperr.Conflict(apperr.CodeInUse, "in use", cause), apperr.KindConflict, apperr.CodeInUse},
		{"validation", apperr.Validation(field), apperr.KindValidation, apperr.CodeValidationFailed},
		{"unauthenticated", apperr.Unauthenticated(apperr.CodeTokenExpired, "expired"), apperr.KindUnauthenticated, apperr.CodeTokenExpired},
		{"forbidden", apperr.Forbidden(apperr.CodeEmailUnverified, "unverified"), apperr.KindForbidden, apperr.CodeEmailUnverified},
		{"rate limited", apperr.RateLimited(apperr.CodeTooManyAttempts, time.Minute), apperr.KindRateLimited, apperr.CodeTooManyAttempts},
		{"precondition failed", apperr.PreconditionFailed("stale"), apperr.KindPreconditionFailed, apperr.CodePreconditionFailed},
		{"precondition required", apperr.PreconditionRequired("missing"), apperr.KindPreconditionRequired, apperr.CodePreconditionRequired},
		{"bad request", apperr.BadRequest("bad", cause), apperr.KindBadRequest, apperr.CodeBadRequest},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			wrapped := fmt.Errorf("use case: %w", tt.err)

			appErr, ok := apperr.As(wrapped)
			if !ok {
				t.Fatalf("As(%v) found no *Error", wrapped)
			}

			if appErr.Kind != tt.kind || appErr.Code != tt.code {
				t.Errorf("kind, code = %v, %q; want %v, %q", appErr.Kind, appErr.Code, tt.kind, tt.code)
			}

			if !apperr.HasKind(wrapped, tt.kind) {
				t.Errorf("HasKind(%v) = false", tt.kind)
			}
		})
	}
}

func TestHasKindRejectsOtherErrors(t *testing.T) {
	t.Parallel()

	for _, err := range []error{nil, errors.New("plain"), apperr.NotFound(nil)} {
		if apperr.HasKind(err, apperr.KindConflict) {
			t.Errorf("HasKind(%v, KindConflict) = true", err)
		}
	}
}

func TestValidationWithoutFieldsIsNil(t *testing.T) {
	t.Parallel()

	if err := apperr.Validation(); err != nil {
		t.Errorf("Validation() = %v, want nil", err)
	}
}

func TestErrorMessageAndCause(t *testing.T) {
	t.Parallel()

	cause := errors.New("duplicate key")

	tests := []struct {
		err  error
		want string
	}{
		{apperr.Conflict(apperr.CodeConflict, "taken", cause), "taken: duplicate key"},
		{apperr.NotFound(nil), "Record not found."},
		{apperr.Validation(apperr.FieldError{Pointer: "/name", Detail: "validation.required"}), "/name: validation.required"},
	}

	for _, tt := range tests {
		if got := tt.err.Error(); got != tt.want {
			t.Errorf("Error() = %q, want %q", got, tt.want)
		}
	}

	if !errors.Is(apperr.Conflict(apperr.CodeConflict, "taken", cause), cause) {
		t.Error("errors.Is(err, cause) = false, want the cause unwrapped")
	}
}

func TestKindString(t *testing.T) {
	t.Parallel()

	want := map[apperr.Kind]string{
		apperr.KindNotFound:             "not_found",
		apperr.KindConflict:             "conflict",
		apperr.KindValidation:           "validation",
		apperr.KindUnauthenticated:      "unauthenticated",
		apperr.KindForbidden:            "forbidden",
		apperr.KindRateLimited:          "rate_limited",
		apperr.KindPreconditionFailed:   "precondition_failed",
		apperr.KindPreconditionRequired: "precondition_required",
		apperr.KindBadRequest:           "bad_request",
		apperr.Kind(0):                  "unknown",
	}

	for kind, name := range want {
		if got := kind.String(); got != name {
			t.Errorf("Kind(%d).String() = %q, want %q", kind, got, name)
		}
	}
}

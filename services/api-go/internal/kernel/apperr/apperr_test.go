package apperr_test

import (
	"errors"
	"fmt"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
)

func TestHasKind(t *testing.T) {
	t.Parallel()

	cause := errors.New("duplicate key")

	tests := []struct {
		name string
		err  error
		kind apperr.Kind
		want bool
	}{
		{"same kind", apperr.Conflict("email taken", cause), apperr.KindConflict, true},
		{"wrapped", fmt.Errorf("register: %w", apperr.NotFound("user", nil)), apperr.KindNotFound, true},
		{"other kind", apperr.NotFound("user", nil), apperr.KindConflict, false},
		{"plain error", cause, apperr.KindConflict, false},
		{"nil", nil, apperr.KindNotFound, false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			if got := apperr.HasKind(tt.err, tt.kind); got != tt.want {
				t.Errorf("HasKind(%v, %v) = %v, want %v", tt.err, tt.kind, got, tt.want)
			}
		})
	}
}

func TestErrorMessageAndCause(t *testing.T) {
	t.Parallel()

	cause := errors.New("duplicate key")

	err := apperr.Conflict("email taken", cause)
	if got, want := err.Error(), "email taken: duplicate key"; got != want {
		t.Errorf("Error() = %q, want %q", got, want)
	}

	if !errors.Is(err, cause) {
		t.Error("errors.Is(err, cause) = false, want the cause unwrapped")
	}

	if got, want := apperr.NotFound("user", nil).Error(), "user"; got != want {
		t.Errorf("Error() without cause = %q, want %q", got, want)
	}
}

func TestKindString(t *testing.T) {
	t.Parallel()

	for kind, want := range map[apperr.Kind]string{
		apperr.KindNotFound: "not_found",
		apperr.KindConflict: "conflict",
		apperr.Kind(0):      "unknown",
	} {
		if got := kind.String(); got != want {
			t.Errorf("Kind(%d).String() = %q, want %q", kind, got, want)
		}
	}
}

package validate_test

import (
	"strings"
	"testing"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/validate"
)

type set struct {
	Order  int
	Effort float64
}

func TestValidatorCollectsEveryFailureWithPointers(t *testing.T) {
	t.Parallel()

	v := validate.New()

	if v.Required("name", " ") {
		t.Error("Required(blank) = true")
	}

	v.Email("email", "Ada <ada@example.com>")
	v.MinLen("password", "short", 8)
	v.MaxLen("title", strings.Repeat("я", 4), 3)
	v.In("client", "toaster", "web", "native")
	v.MinItems("activities", 0, 1)
	v.Object("profile", func(v *validate.Validator) {
		v.Required("a/b~c", "")
	})

	sets := []set{{Order: 1, Effort: 5}, {Order: 2, Effort: 7.5}}
	v.Nested("activities", 0, func(v *validate.Validator) {
		for i, s := range sets {
			v.Nested("sets", i, func(v *validate.Validator) {
				v.Range("effort_value", s.Effort, 0, 6)
			})
		}
	})

	v.Check(false, "exercise_id", apperr.FieldNotFound, "The selected :attribute is invalid.", map[string]string{"attribute": "exercise"})

	appErr, ok := apperr.As(v.Err())
	if !ok || appErr.Kind != apperr.KindValidation {
		t.Fatalf("Err() = %v, want a validation error", v.Err())
	}

	type entry struct {
		Pointer string
		Code    apperr.FieldCode
		Detail  string
		Args    map[string]string
	}

	got := make([]entry, 0, len(appErr.Fields))
	for _, f := range appErr.Fields {
		got = append(got, entry(f))
	}

	want := []entry{
		{"/name", apperr.FieldRequired, "validation.required", map[string]string{"attribute": "name"}},
		{"/email", apperr.FieldInvalidFormat, "validation.email", map[string]string{"attribute": "email"}},
		{"/password", apperr.FieldTooShort, "validation.min.string", map[string]string{"attribute": "password", "min": "8"}},
		{"/title", apperr.FieldTooLong, "validation.max.string", map[string]string{"attribute": "title", "max": "3"}},
		{"/client", apperr.FieldInvalidValue, "validation.in", map[string]string{"attribute": "client"}},
		{"/activities", apperr.FieldTooShort, "validation.min.array", map[string]string{"attribute": "activities", "min": "1"}},
		{"/profile/a~1b~0c", apperr.FieldRequired, "validation.required", map[string]string{"attribute": "a/b~c"}},
		{"/activities/0/sets/1/effort_value", apperr.FieldOutOfRange, "validation.between.numeric", map[string]string{"attribute": "effort value", "min": "0", "max": "6"}},
		{"/exercise_id", apperr.FieldNotFound, "The selected :attribute is invalid.", map[string]string{"attribute": "exercise"}},
	}

	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("fields (-want +got):\n%s", diff)
	}
}

func TestCheckItemPointsAtTheElement(t *testing.T) {
	t.Parallel()

	v := validate.New()
	v.CheckItem(true, "category_ids", 0, apperr.FieldNotFound, "validation.exists")
	v.CheckItem(false, "category_ids", 1, apperr.FieldNotFound, "validation.exists")

	appErr, ok := apperr.As(v.Err())
	if !ok || len(appErr.Fields) != 1 {
		t.Fatalf("err = %v", v.Err())
	}

	want := apperr.FieldError{Pointer: "/category_ids/1", Code: apperr.FieldNotFound, Detail: "validation.exists", Args: map[string]string{"attribute": "category_ids.1"}}
	if diff := cmp.Diff(want, appErr.Fields[0]); diff != "" {
		t.Errorf("field (-want +got):\n%s", diff)
	}
}

func TestValidatorPassesValidInput(t *testing.T) {
	t.Parallel()

	v := validate.New()

	checks := []bool{
		v.Required("name", "Ada"),
		v.Email("email", "ada@example.com"),
		v.MinLen("password", "long enough", 8),
		v.MaxLen("title", "яяя", 3),
		v.In("client", "web", "web", "native"),
		v.Range("zone", 5, 1, 5),
		v.MinItems("sets", 1, 1),
	}

	for i, ok := range checks {
		if !ok {
			t.Errorf("check %d failed on valid input", i)
		}
	}

	if err := v.Err(); err != nil {
		t.Errorf("Err() = %v, want nil", err)
	}
}

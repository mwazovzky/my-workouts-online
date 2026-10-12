package httpapi

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/getkin/kin-openapi/openapi3filter"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// The validator presents the 3.1 contract as 3.0 (see newRequestValidator).
// This guards that kin's own validator still honours `type: [X, 'null']`
// and the bounds next to it.
func TestNullableFields(t *testing.T) {
	t.Parallel()

	spec, err := gen.GetSpec()
	if err != nil {
		t.Fatal(err)
	}

	v := newRequestValidator(spec, nil, nil)

	tests := []struct {
		value string
		want  apperr.FieldCode // "" means no error on the field
	}{
		{"null", ""},
		{"90", ""},
		{`"soon"`, apperr.FieldInvalidFormat},
		{"5000", apperr.FieldOutOfRange},
	}

	for _, tt := range tests {
		body := `{"equipment_id": 1, "effort_type": "repetitions", "rest_time_seconds": ` + tt.value + `}`
		r := httptest.NewRequestWithContext(t.Context(), http.MethodPost, "/api/v1/admin/exercises", strings.NewReader(body))
		r.Header.Set("Content-Type", "application/json")

		err := openapi3filter.ValidateRequest(r.Context(), &openapi3filter.RequestValidationInput{
			Request: r, Route: v.routes["POST /api/v1/admin/exercises"], Options: v.options,
		})

		var got apperr.FieldCode

		if appErr, ok := apperr.As(toAppError(err)); ok {
			for _, f := range appErr.Fields {
				if f.Pointer == "/rest_time_seconds" {
					got = f.Code
				}
			}
		}

		if got != tt.want {
			t.Errorf("rest_time_seconds %s: code = %q, want %q (error: %v)", tt.value, got, tt.want, err)
		}
	}
}

func TestAttributeOf(t *testing.T) {
	t.Parallel()

	for pointer, want := range map[string]string{
		"/activities/0/sets/1/effort_value": "effort value",
		"/email":                            "email",
		"/a~1b":                             "a/b",
		"":                                  "input",
		"/0":                                "input",
	} {
		if got := attributeOf(pointer); got != want {
			t.Errorf("attributeOf(%q) = %q, want %q", pointer, got, want)
		}
	}
}

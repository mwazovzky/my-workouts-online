// Package validate builds apperr validation errors from use-case input. A
// Validator collects every failure (it does not stop at the first), with a
// JSON Pointer per field and the contract's field codes; messages are i18n
// keys from Laravel's validation catalog, translated by the HTTP boundary.
//
//	v := validate.New()
//	if v.Required("name", in.Name) {
//		v.MaxLen("name", in.Name, 255)
//	}
//	for i, a := range in.Activities {
//		v.Nested("activities", i, func(v *validate.Validator) {
//			v.Range("order", float64(a.Order), 1, 100)
//		})
//	}
//	return v.Err()
//
// Each check returns whether the value passed, so a caller can skip checks
// that make no sense after a failure (Laravel's `bail`).
package validate

import (
	"net/mail"
	"slices"
	"strconv"
	"strings"
	"unicode/utf8"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
)

// Validator collects field errors under a pointer prefix.
type Validator struct {
	prefix string
	errs   *[]apperr.FieldError
}

// New returns an empty Validator for a request body.
func New() *Validator {
	return &Validator{errs: &[]apperr.FieldError{}}
}

// Err returns the collected failures as one apperr validation error, or nil.
func (v *Validator) Err() error {
	return apperr.Validation(*v.errs...)
}

// Nested runs fn for the element index of the array field, with pointers
// under /field/index.
func (v *Validator) Nested(field string, index int, fn func(*Validator)) {
	fn(&Validator{prefix: v.pointer(field) + "/" + strconv.Itoa(index), errs: v.errs})
}

// Object runs fn for the object field, with pointers under /field.
func (v *Validator) Object(field string, fn func(*Validator)) {
	fn(&Validator{prefix: v.pointer(field), errs: v.errs})
}

// Fail records a failure of field with a custom message key and args; the
// field's display name is added as :attribute unless args sets it.
func (v *Validator) Fail(field string, code apperr.FieldCode, detail string, args map[string]string) {
	all := map[string]string{"attribute": Attribute(field)}
	for key, value := range args {
		all[key] = value
	}

	*v.errs = append(*v.errs, apperr.FieldError{Pointer: v.pointer(field), Code: code, Detail: detail, Args: all})
}

// Check records a failure when ok is false and returns ok.
func (v *Validator) Check(ok bool, field string, code apperr.FieldCode, detail string, args map[string]string) bool {
	if !ok {
		v.Fail(field, code, detail, args)
	}

	return ok
}

// Required fails when value is empty or only whitespace.
func (v *Validator) Required(field, value string) bool {
	return v.Check(strings.TrimSpace(value) != "", field, apperr.FieldRequired, "validation.required", nil)
}

// Email fails when value is not a plain address (see IsEmail).
func (v *Validator) Email(field, value string) bool {
	return v.Check(IsEmail(value), field, apperr.FieldInvalidFormat, "validation.email", nil)
}

// IsEmail reports whether value is a plain address: name@domain, with no
// display name or angle brackets. The contract's `format: email` uses it too.
func IsEmail(value string) bool {
	addr, err := mail.ParseAddress(value)

	return err == nil && addr.Name == "" && addr.Address == value
}

// MinLen fails when value has fewer than min characters.
func (v *Validator) MinLen(field, value string, minimum int) bool {
	return v.Check(utf8.RuneCountInString(value) >= minimum, field, apperr.FieldTooShort,
		"validation.min.string", map[string]string{"min": strconv.Itoa(minimum)})
}

// MaxLen fails when value has more than max characters.
func (v *Validator) MaxLen(field, value string, maximum int) bool {
	return v.Check(utf8.RuneCountInString(value) <= maximum, field, apperr.FieldTooLong,
		"validation.max.string", map[string]string{"max": strconv.Itoa(maximum)})
}

// In fails when value is not one of allowed.
func (v *Validator) In(field, value string, allowed ...string) bool {
	return v.Check(slices.Contains(allowed, value), field, apperr.FieldInvalidValue, "validation.in", nil)
}

// Range fails when value is outside [min, max].
func (v *Validator) Range(field string, value, minimum, maximum float64) bool {
	return v.Check(value >= minimum && value <= maximum, field, apperr.FieldOutOfRange,
		"validation.between.numeric", map[string]string{"min": formatNumber(minimum), "max": formatNumber(maximum)})
}

// MinItems fails when an array field has fewer than min elements.
func (v *Validator) MinItems(field string, count, minimum int) bool {
	return v.Check(count >= minimum, field, apperr.FieldOutOfRange,
		"validation.min.array", map[string]string{"min": strconv.Itoa(minimum)})
}

func (v *Validator) pointer(field string) string {
	return v.prefix + "/" + Escape(field)
}

// Escape escapes one JSON Pointer segment (RFC 6901: ~ is ~0, / is ~1).
func Escape(segment string) string {
	return strings.NewReplacer("~", "~0", "/", "~1").Replace(segment)
}

// Attribute is a field's display name in messages, as Laravel derives it:
// the field name with underscores as spaces (effort_value is "effort
// value"). The HTTP boundary translates it, so a field can be named
// by a catalog key.
func Attribute(field string) string {
	return strings.ReplaceAll(field, "_", " ")
}

func formatNumber(n float64) string {
	return strconv.FormatFloat(n, 'f', -1, 64)
}

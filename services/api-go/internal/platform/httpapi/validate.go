package httpapi

import (
	"errors"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"github.com/getkin/kin-openapi/openapi3"
	"github.com/getkin/kin-openapi/openapi3filter"
	"github.com/getkin/kin-openapi/routers"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/validate"
)

// requestValidator checks each request against its contract operation
// before a handler sees it, so shape mistakes (wrong types, unknown fields,
// out-of-range parameters, malformed JSON) are 422s with pointers and never
// reach a use case. Handlers still validate business rules.
type requestValidator struct {
	// routes maps a ServeMux pattern ("GET /api/v1/meta") to its operation;
	// the generated server registers exactly these patterns.
	routes  map[string]*routers.Route
	options *openapi3filter.Options
	rs      *Responder
}

var pathParam = regexp.MustCompile(`\{([^}]+)\}`)

func newRequestValidator(spec *openapi3.T, rs *Responder) *requestValidator {
	// For a 3.1 document kin-openapi validates with its JSON Schema 2020
	// validator whenever a schema compiles standalone, and that validator
	// reports failures as English text without the keyword. Presented as 3.0,
	// every schema goes through kin's own validator, which names the keyword
	// (needed for the field code) and understands the 3.1 `type: [X, 'null']`
	// arrays, the only 3.1 construct the contract uses. TestNullableFields
	// guards that assumption.
	legacy := *spec
	legacy.OpenAPI = "3.0.3"
	spec = &legacy

	v := &requestValidator{
		routes: map[string]*routers.Route{},
		options: &openapi3filter.Options{
			MultiError: true,
			// Authentication is the authorize middleware's job.
			AuthenticationFunc: openapi3filter.NoopAuthenticationFunc,
			// kin checks no string format unless asked; email is the one the
			// request schemas rely on.
			SchemaValidationOptions: []openapi3.SchemaValidationOption{
				openapi3.EnableFormatValidation(),
				openapi3.WithStringFormatValidator("email", openapi3.NewCallbackValidator(func(value string) error {
					if !validate.IsEmail(value) {
						return errors.New("not an email address")
					}

					return nil
				})),
			},
		},
		rs: rs,
	}

	for path, item := range spec.Paths.Map() {
		for method, operation := range item.Operations() {
			v.routes[method+" "+path] = &routers.Route{
				Spec: spec, Path: path, PathItem: item, Method: method, Operation: operation,
			}
		}
	}

	return v
}

// middleware is a per-operation middleware for the generated server.
func (v *requestValidator) middleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		route, ok := v.routes[r.Pattern]
		if !ok {
			next.ServeHTTP(w, r)

			return
		}

		params := map[string]string{}
		for _, match := range pathParam.FindAllStringSubmatch(route.Path, -1) {
			params[match[1]] = r.PathValue(match[1])
		}

		err := openapi3filter.ValidateRequest(r.Context(), &openapi3filter.RequestValidationInput{
			Request:    r,
			PathParams: params,
			Route:      route,
			Options:    v.options,
		})
		if err != nil {
			v.rs.Error(w, r, toAppError(err))

			return
		}

		next.ServeHTTP(w, r)
	})
}

// toAppError converts openapi3filter's errors into one validation error with
// a field entry per failure.
func toAppError(err error) error {
	var fields []apperr.FieldError

	collectFieldErrors(err, &fields)

	if len(fields) == 0 {
		return apperr.BadRequest("Bad request", err)
	}

	return apperr.Validation(fields...)
}

// collectFieldErrors walks openapi3filter's error tree: MultiErrors of
// RequestErrors (one per parameter or body), each holding a SchemaError or
// a MultiError of them. The walk switches on the concrete types because
// errors.As would stop at the first match inside a MultiError.
func collectFieldErrors(err error, fields *[]apperr.FieldError) {
	switch e := err.(type) { //nolint:errorlint // walking a known error tree, not matching a wrapped error
	case openapi3.MultiError:
		for _, inner := range e {
			collectFieldErrors(inner, fields)
		}
	case *openapi3filter.RequestError:
		collectRequestError(e, fields)
	}
}

func collectRequestError(reqErr *openapi3filter.RequestError, fields *[]apperr.FieldError) {
	prefix := ""
	if reqErr.Parameter != nil {
		prefix = "/" + validate.Escape(reqErr.Parameter.Name)
	}

	var parseErr *openapi3filter.ParseError

	switch {
	case reqErr.Err == nil, errors.Is(reqErr.Err, openapi3filter.ErrInvalidRequired):
		*fields = append(*fields, fieldError(prefix, apperr.FieldRequired, "validation.required", nil))
	case reqErr.RequestBody != nil && errors.As(reqErr.Err, &parseErr):
		*fields = append(*fields, apperr.FieldError{
			Pointer: "", Code: apperr.FieldInvalidFormat, Detail: "The request body must be valid JSON.",
		})
	default:
		before := len(*fields)
		collectSchemaErrors(prefix, reqErr.Err, fields)

		if len(*fields) == before {
			*fields = append(*fields, fieldError(prefix, apperr.FieldInvalidFormat, "validation.regex", nil))
		}
	}
}

func collectSchemaErrors(prefix string, err error, fields *[]apperr.FieldError) {
	switch e := err.(type) { //nolint:errorlint // walking a known error tree, not matching a wrapped error
	case openapi3.MultiError:
		for _, inner := range e {
			collectSchemaErrors(prefix, inner, fields)
		}
	case *openapi3.SchemaError:
		pointer := prefix
		for _, segment := range e.JSONPointer() {
			pointer += "/" + validate.Escape(segment)
		}

		*fields = append(*fields, schemaFieldError(pointer, e))
	}
}

// schemaFieldError maps a failed JSON Schema keyword to a field code and the
// Laravel message for the equivalent rule.
func schemaFieldError(pointer string, e *openapi3.SchemaError) apperr.FieldError {
	s := e.Schema

	switch e.SchemaField {
	case "required":
		return fieldError(pointer, apperr.FieldRequired, "validation.required", nil)
	case "additionalProperties", "properties":
		// kin points at the object; the property is named only in the reason.
		if name, ok := unsupportedProperty(e.Reason); ok {
			pointer += "/" + validate.Escape(name)
		}

		return fieldError(pointer, apperr.FieldUnknownField, "validation.unknown_field", nil)
	case "enum", "const":
		return fieldError(pointer, apperr.FieldInvalidValue, "validation.in", nil)
	case "type":
		return fieldError(pointer, apperr.FieldInvalidFormat, typeMessage(s), nil)
	case "format":
		return fieldError(pointer, apperr.FieldInvalidFormat, formatMessage(s.Format), nil)
	case "pattern":
		return fieldError(pointer, apperr.FieldInvalidFormat, "validation.regex", nil)
	case "minLength":
		return fieldError(pointer, apperr.FieldTooShort, "validation.min.string", map[string]string{"min": strconv.FormatUint(s.MinLength, 10)})
	case "maxLength":
		return fieldError(pointer, apperr.FieldTooLong, "validation.max.string", map[string]string{"max": uintPtr(s.MaxLength)})
	case "minimum", "exclusiveMinimum":
		return fieldError(pointer, apperr.FieldOutOfRange, "validation.min.numeric", map[string]string{"min": floatPtr(s.Min)})
	case "maximum", "exclusiveMaximum":
		return fieldError(pointer, apperr.FieldOutOfRange, "validation.max.numeric", map[string]string{"max": floatPtr(s.Max)})
	case "minItems":
		return fieldError(pointer, apperr.FieldOutOfRange, "validation.min.array", map[string]string{"min": strconv.FormatUint(s.MinItems, 10)})
	case "maxItems":
		return fieldError(pointer, apperr.FieldOutOfRange, "validation.max.array", map[string]string{"max": uintPtr(s.MaxItems)})
	case "uniqueItems":
		return fieldError(pointer, apperr.FieldNotUnique, "validation.distinct", nil)
	default:
		return fieldError(pointer, apperr.FieldInvalidValue, "validation.in", nil)
	}
}

var unsupported = regexp.MustCompile(`^property "(.+)" is unsupported$`)

// unsupportedProperty extracts the property name from kin's
// additionalProperties reason.
func unsupportedProperty(reason string) (string, bool) {
	match := unsupported.FindStringSubmatch(reason)
	if match == nil {
		return "", false
	}

	return match[1], true
}

// fieldError builds a FieldError whose :attribute is the last named
// segment of pointer (/activities/0/sets/1/effort_value is "effort value").
func fieldError(pointer string, code apperr.FieldCode, detail string, args map[string]string) apperr.FieldError {
	all := map[string]string{"attribute": attributeOf(pointer)}
	for k, v := range args {
		all[k] = v
	}

	return apperr.FieldError{Pointer: pointer, Code: code, Detail: detail, Args: all}
}

func attributeOf(pointer string) string {
	segments := strings.Split(pointer, "/")
	for i := len(segments) - 1; i >= 0; i-- {
		if _, err := strconv.Atoi(segments[i]); err != nil && segments[i] != "" {
			segment := strings.NewReplacer("~1", "/", "~0", "~").Replace(segments[i])

			return validate.Attribute(segment)
		}
	}

	return "input"
}

func typeMessage(s *openapi3.Schema) string {
	switch {
	case s.Type.Includes(openapi3.TypeInteger):
		return "validation.integer"
	case s.Type.Includes(openapi3.TypeNumber):
		return "validation.numeric"
	case s.Type.Includes(openapi3.TypeBoolean):
		return "validation.boolean"
	case s.Type.Includes(openapi3.TypeArray):
		return "validation.array"
	case s.Type.Includes(openapi3.TypeObject):
		return "The :attribute field must be an object."
	default:
		return "validation.string"
	}
}

func formatMessage(format string) string {
	switch format {
	case "email":
		return "validation.email"
	case "uuid":
		return "validation.uuid"
	case "date", "date-time":
		return "validation.date"
	case "uri", "url":
		return "validation.url"
	default:
		return "validation.regex"
	}
}

func uintPtr(n *uint64) string {
	if n == nil {
		return ""
	}

	return strconv.FormatUint(*n, 10)
}

func floatPtr(n *float64) string {
	if n == nil {
		return ""
	}

	return strconv.FormatFloat(*n, 'f', -1, 64)
}

package httpapi

import (
	"encoding/json"
	"errors"
	"math"
	"net/http"
	"slices"
	"strconv"
	"strings"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// ProblemContentType is the media type of every error response.
const ProblemContentType = "application/problem+json"

// ErrNotImplemented is returned by contract operations no story has
// implemented yet; the responder answers 501.
var ErrNotImplemented = errors.New("operation not implemented")

// titles are the stable English Problem titles, one per contract code.
var titles = map[apperr.Code]string{
	apperr.CodeBadRequest:              "Bad request",
	apperr.CodeUnauthenticated:         "Unauthenticated",
	apperr.CodeInvalidCredentials:      "Invalid credentials",
	apperr.CodeTokenExpired:            "Access token expired",
	apperr.CodeRefreshTokenMissing:     "Refresh token missing",
	apperr.CodeRefreshTokenInvalid:     "Refresh token invalid",
	apperr.CodeRefreshTokenExpired:     "Refresh token expired",
	apperr.CodeRefreshTokenReused:      "Refresh token reuse detected",
	apperr.CodeForbidden:               "Forbidden",
	apperr.CodeEmailUnverified:         "Email address not verified",
	apperr.CodeNotFound:                "Not found",
	apperr.CodeMethodNotAllowed:        "Method not allowed",
	apperr.CodeConflict:                "Conflict",
	apperr.CodeInUse:                   "Resource in use",
	apperr.CodePreconditionFailed:      "Precondition failed",
	apperr.CodePreconditionRequired:    "Precondition required",
	apperr.CodeValidationFailed:        "Validation failed",
	apperr.CodeTooManyRequests:         "Too many requests",
	apperr.CodeTooManyAttempts:         "Too many attempts",
	apperr.CodeInvalidVerificationLink: "Invalid verification link",
	apperr.CodeInvalidSignature:        "Invalid signature",
	apperr.CodeInternalError:           "Internal server error",
}

// statuses map each apperr kind to its HTTP status.
var statuses = map[apperr.Kind]int{
	apperr.KindNotFound:             http.StatusNotFound,
	apperr.KindConflict:             http.StatusConflict,
	apperr.KindValidation:           http.StatusUnprocessableEntity,
	apperr.KindUnauthenticated:      http.StatusUnauthorized,
	apperr.KindForbidden:            http.StatusForbidden,
	apperr.KindRateLimited:          http.StatusTooManyRequests,
	apperr.KindPreconditionFailed:   http.StatusPreconditionFailed,
	apperr.KindPreconditionRequired: http.StatusPreconditionRequired,
	apperr.KindBadRequest:           http.StatusBadRequest,
}

// Responder writes JSON bodies and Problem Details.
type Responder struct {
	// problemBase is APP_URL + "/problems/"; a Problem's type is it plus the code.
	problemBase string
	bundle      *i18n.Bundle
}

// NewResponder returns a Responder for an API served at appURL.
func NewResponder(appURL string, bundle *i18n.Bundle) *Responder {
	return &Responder{problemBase: strings.TrimRight(appURL, "/") + "/problems/", bundle: bundle}
}

// JSON writes body as JSON with status.
func (rs *Responder) JSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}

// Error writes err as a Problem: an apperr.Error with its kind's status and
// code, ErrNotImplemented as 501, anything else as a 500 that is logged and
// never described to the client.
func (rs *Responder) Error(w http.ResponseWriter, r *http.Request, err error) {
	appErr, ok := apperr.As(err)

	switch {
	case ok:
		rs.appError(w, r, appErr)
	case errors.Is(err, ErrNotImplemented):
		rs.Problem(w, r, http.StatusNotImplemented, apperr.CodeInternalError, "This operation is not implemented yet.", nil)
	default:
		Logger(r.Context()).ErrorContext(r.Context(), "request failed", "error", err.Error())
		rs.Problem(w, r, http.StatusInternalServerError, apperr.CodeInternalError, "Server Error", nil)
	}
}

func (rs *Responder) appError(w http.ResponseWriter, r *http.Request, appErr *apperr.Error) {
	status, ok := statuses[appErr.Kind]
	if !ok {
		status = http.StatusInternalServerError
	}

	if appErr.Kind == apperr.KindRateLimited {
		w.Header().Set("Retry-After", strconv.Itoa(int(math.Ceil(appErr.RetryAfter.Seconds()))))
	}

	if appErr.Kind == apperr.KindValidation {
		rs.validation(w, r, appErr.Fields)

		return
	}

	rs.Problem(w, r, status, appErr.Code, appErr.Detail, appErr.Args)
}

// Problem writes a Problem with status and code; detail is a message key
// translated into the request's locale with args.
func (rs *Responder) Problem(w http.ResponseWriter, r *http.Request, status int, code apperr.Code, detail string, args map[string]string) {
	instance := rs.instance(r)
	rs.write(w, status, gen.Problem{
		Type:     rs.problemBase + string(code),
		Title:    titles[code],
		Status:   int32(status), //nolint:gosec // G115: HTTP status codes fit in int32
		Detail:   rs.translate(r, detail, args),
		Instance: &instance,
		Code:     gen.ErrorCode(code),
	})
}

// validation writes a 422 Problem listing fields. Its detail is the first
// field's message plus "(and N more errors)", as Laravel summarises.
func (rs *Responder) validation(w http.ResponseWriter, r *http.Request, fields []apperr.FieldError) {
	instance := rs.instance(r)
	body := gen.ValidationProblem{
		Type:     rs.problemBase + string(apperr.CodeValidationFailed),
		Title:    titles[apperr.CodeValidationFailed],
		Status:   http.StatusUnprocessableEntity,
		Instance: &instance,
		Code:     gen.ValidationProblemCode(apperr.CodeValidationFailed),
	}

	// The generated item type is an anonymous struct; grow in place rather
	// than restate it.
	body.Errors = slices.Grow(body.Errors, len(fields))[:len(fields)]
	for i, field := range fields {
		body.Errors[i].Pointer = field.Pointer
		body.Errors[i].Code = gen.FieldErrorCode(field.Code)
		body.Errors[i].Detail = rs.translate(r, field.Detail, field.Args)
	}

	if len(body.Errors) > 0 {
		body.Detail = body.Errors[0].Detail
	}

	if more := len(body.Errors) - 1; more > 0 {
		key := "(and :count more errors)"
		if more == 1 {
			key = "(and :count more error)"
		}

		body.Detail += " " + rs.translate(r, key, map[string]string{"count": strconv.Itoa(more)})
	}

	rs.write(w, http.StatusUnprocessableEntity, body)
}

func (rs *Responder) write(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", ProblemContentType)
	w.Header().Set("Cache-Control", "no-cache, private")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}

// translate localizes a message key. The :attribute argument is a field's
// display name and is itself translated, so a field can be named by a
// catalog key ("Theme" → "Тема").
func (rs *Responder) translate(r *http.Request, key string, args map[string]string) string {
	locale := i18n.FromContext(r.Context())

	if attribute, ok := args["attribute"]; ok {
		translated := make(map[string]string, len(args))
		for k, v := range args {
			translated[k] = v
		}

		translated["attribute"] = rs.bundle.T(locale, attribute, nil)
		args = translated
	}

	return rs.bundle.T(locale, key, args)
}

// instance is the request path plus the request id: /api/v1/workouts/42#<uuid>.
func (rs *Responder) instance(r *http.Request) string {
	return r.URL.Path + "#" + RequestID(r.Context())
}

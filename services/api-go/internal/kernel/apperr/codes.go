package apperr

// Code is a Problem's `code`: the contract's ErrorCode enum. A backend may
// not emit a code outside this list; the httpapi tests compare it with the
// generated enum.
type Code string

// The contract's ErrorCode values.
const (
	CodeBadRequest              Code = "bad_request"
	CodeUnauthenticated         Code = "unauthenticated"
	CodeInvalidCredentials      Code = "invalid_credentials" //nolint:gosec // G101: an error code, not a credential
	CodeTokenExpired            Code = "token_expired"
	CodeRefreshTokenMissing     Code = "refresh_token_missing"
	CodeRefreshTokenInvalid     Code = "refresh_token_invalid"
	CodeRefreshTokenExpired     Code = "refresh_token_expired"
	CodeRefreshTokenReused      Code = "refresh_token_reused"
	CodeForbidden               Code = "forbidden"
	CodeEmailUnverified         Code = "email_unverified"
	CodeNotFound                Code = "not_found"
	CodeMethodNotAllowed        Code = "method_not_allowed"
	CodeConflict                Code = "conflict"
	CodeInUse                   Code = "in_use"
	CodePreconditionFailed      Code = "precondition_failed"
	CodePreconditionRequired    Code = "precondition_required"
	CodeValidationFailed        Code = "validation_failed"
	CodeTooManyRequests         Code = "too_many_requests"
	CodeTooManyAttempts         Code = "too_many_attempts"
	CodeInvalidVerificationLink Code = "invalid_verification_link"
	CodeInvalidSignature        Code = "invalid_signature"
	CodeInternalError           Code = "internal_error"
)

// Codes lists every Code, in the contract's order.
func Codes() []Code {
	return []Code{
		CodeBadRequest, CodeUnauthenticated, CodeInvalidCredentials, CodeTokenExpired,
		CodeRefreshTokenMissing, CodeRefreshTokenInvalid, CodeRefreshTokenExpired, CodeRefreshTokenReused,
		CodeForbidden, CodeEmailUnverified, CodeNotFound, CodeMethodNotAllowed, CodeConflict, CodeInUse,
		CodePreconditionFailed, CodePreconditionRequired, CodeValidationFailed, CodeTooManyRequests,
		CodeTooManyAttempts, CodeInvalidVerificationLink, CodeInvalidSignature, CodeInternalError,
	}
}

// FieldCode is a validation error entry's `code`: the contract's
// FieldErrorCode enum.
type FieldCode string

// The contract's FieldErrorCode values.
const (
	FieldRequired      FieldCode = "required"
	FieldInvalidFormat FieldCode = "invalid_format"
	FieldInvalidValue  FieldCode = "invalid_value"
	FieldTooShort      FieldCode = "too_short"
	FieldTooLong       FieldCode = "too_long"
	FieldOutOfRange    FieldCode = "out_of_range"
	FieldNotUnique     FieldCode = "not_unique"
	FieldNotFound      FieldCode = "not_found"
	FieldMismatch      FieldCode = "mismatch"
	FieldUnknownField  FieldCode = "unknown_field"
)

// FieldCodes lists every FieldCode, in the contract's order.
func FieldCodes() []FieldCode {
	return []FieldCode{
		FieldRequired, FieldInvalidFormat, FieldInvalidValue, FieldTooShort, FieldTooLong,
		FieldOutOfRange, FieldNotUnique, FieldNotFound, FieldMismatch, FieldUnknownField,
	}
}

// FieldError is one rejected field of a validation error.
type FieldError struct {
	// Pointer is an RFC 6901 JSON Pointer into the request body
	// (/activities/0/sets/1/effort_value), or /name for a query parameter.
	// The empty pointer means the whole body.
	Pointer string
	Code    FieldCode
	// Detail is the English message key; Args fill its :placeholders.
	Detail string
	Args   map[string]string
}

package domain

import "github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"

// The token failures, with the codes clients switch on: the web SPA and the
// iOS app refresh on token_expired and sign out on the refresh_token_* codes.

// ErrUnauthenticated is a missing, malformed or untrusted access token, or
// one whose user no longer exists.
func ErrUnauthenticated() error {
	return apperr.Unauthenticated(apperr.CodeUnauthenticated, "Unauthenticated.")
}

// ErrAccessTokenExpired is a valid access token past its expiry.
func ErrAccessTokenExpired() error {
	return apperr.Unauthenticated(apperr.CodeTokenExpired, "Unauthenticated.")
}

// ErrRefreshTokenMissing is a refresh without a token.
func ErrRefreshTokenMissing() error {
	return apperr.Unauthenticated(apperr.CodeRefreshTokenMissing, "Refresh token missing.")
}

// ErrRefreshTokenInvalid is an unknown or revoked refresh token.
func ErrRefreshTokenInvalid() error {
	return apperr.Unauthenticated(apperr.CodeRefreshTokenInvalid, "Refresh token invalid.")
}

// ErrRefreshTokenExpired is a refresh token past its expiry.
func ErrRefreshTokenExpired() error {
	return apperr.Unauthenticated(apperr.CodeRefreshTokenExpired, "Refresh token expired.")
}

// ErrRefreshTokenReused is a refresh token presented after it was rotated;
// its family has been revoked.
func ErrRefreshTokenReused() error {
	return apperr.Unauthenticated(apperr.CodeRefreshTokenReused, "Refresh token reuse detected.")
}

// ErrInvalidCredentials is a login whose email and password do not match.
func ErrInvalidCredentials() error {
	return apperr.Unauthenticated(apperr.CodeInvalidCredentials, "auth.failed")
}

// Package identity is the identity module: users, roles, access tokens,
// refresh-token families, email verification, password reset and the
// profile. It owns the users, roles, role_user, refresh_tokens and
// password_reset_tokens tables and depends on no other module.
//
//	domain/            User, RefreshToken, PasswordReset, AccessClaims, TokenPair
//	ports/             what the use cases need: repositories, hasher, signer, attempts, links, notifier
//	app/               the use cases (Service) and the principal resolver
//	adapters/postgres  repositories over sqlc (sql/identity)
//	adapters/bcrypt    password hashing
//	adapters/jwt       Ed25519 access tokens and the JWKS document (jwx)
//	adapters/links     signed verification and reset links (ADR 0008)
//	adapters/notify    the verification and reset emails
//	adapters/http      the identity operations of the contract (Auth and Me tags, getJwks)
package identity

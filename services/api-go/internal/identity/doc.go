// Package identity is the identity module: users, roles, access tokens,
// refresh-token families, and (story 5) email verification, password reset
// and the profile. It owns the users, roles, role_user, refresh_tokens and
// password_reset_tokens tables and depends on no other module.
//
//	domain/            User, RefreshToken, AccessClaims, TokenPair
//	ports/             what the use cases need: repositories, hasher, signer, attempts
//	app/               the use cases (Service) and the principal resolver
//	adapters/postgres  repositories over sqlc (sql/identity)
//	adapters/bcrypt    password hashing
//	adapters/jwt       Ed25519 access tokens and the JWKS document (jwx)
//	adapters/http      the contract operations getJwks, register, login, refresh, logout, reauth
package identity

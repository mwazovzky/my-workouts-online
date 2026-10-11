// Package postgres is the identity module's Postgres adapter: the Users and
// RefreshTokens repositories over the sqlc queries in sql/identity
// (generated into sqlcgen). Every call takes its connection from the
// Transactor, so it joins the transaction a use case opened.
package postgres

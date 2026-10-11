// Package bcrypt hashes passwords with bcrypt. It verifies Laravel's $2y$
// hashes too, so accounts could one day be imported from the PHP API.
package bcrypt

import (
	"fmt"

	"golang.org/x/crypto/bcrypt"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
)

// Hasher implements ports.PasswordHasher.
type Hasher struct {
	cost int
}

var _ ports.PasswordHasher = Hasher{}

// New returns a Hasher with cost (BCRYPT_ROUNDS; tests use the minimum).
func New(cost int) Hasher {
	return Hasher{cost: cost}
}

// Hash implements ports.PasswordHasher.
func (h Hasher) Hash(password string) (string, error) {
	hash, err := bcrypt.GenerateFromPassword([]byte(password), h.cost)
	if err != nil {
		return "", fmt.Errorf("bcrypt: %w", err)
	}

	return string(hash), nil
}

// Matches implements ports.PasswordHasher. PHP's $2y$ prefix is the same
// algorithm as $2a$ and $2b$.
func (h Hasher) Matches(hash, password string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(password)) == nil
}

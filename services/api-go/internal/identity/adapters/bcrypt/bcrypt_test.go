package bcrypt_test

import (
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/bcrypt"
)

func TestHashAndMatch(t *testing.T) {
	t.Parallel()

	h := bcrypt.New(4)

	hash, err := h.Hash("secret123")
	if err != nil {
		t.Fatal(err)
	}

	if !h.Matches(hash, "secret123") || h.Matches(hash, "secret124") {
		t.Error("Matches() does not tell the right password from a wrong one")
	}
}

// A hash PHP produced with password_hash("password", PASSWORD_BCRYPT), as
// Laravel's Hash::make does.
func TestMatchesLaravelHashes(t *testing.T) {
	t.Parallel()

	const laravel = "$2y$12$frSo7.5PcNIZIspjBzyG3uu0hz4BRpdzPxDN83zMrThDLWygKKhnW"

	h := bcrypt.New(4)
	if !h.Matches(laravel, "password") || h.Matches(laravel, "Password") {
		t.Error("a Laravel $2y$ hash does not verify")
	}

	if h.Matches("not a hash", "password") {
		t.Error("a malformed hash matched")
	}
}

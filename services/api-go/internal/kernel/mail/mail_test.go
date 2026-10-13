package mail_test

import (
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
)

func TestAddressString(t *testing.T) {
	t.Parallel()

	tests := map[mail.Address]string{
		{Email: "jane@example.com"}:                        "jane@example.com",
		{Name: "Jane Doe", Email: "jane@example.com"}:      "Jane Doe <jane@example.com>",
		{Name: "Doe, Jane", Email: "jane@example.com"}:     `"Doe, Jane" <jane@example.com>`,
		{Name: `Jane "JD" Doe`, Email: "jane@example.com"}: `"Jane \"JD\" Doe" <jane@example.com>`,
		{Name: "Жанна via My Workouts", Email: "a@x.y"}:    "Жанна via My Workouts <a@x.y>",
	}

	for address, want := range tests {
		if got := address.String(); got != want {
			t.Errorf("%+v.String() = %q, want %q", address, got, want)
		}
	}
}

package auth_test

import (
	"context"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
)

func TestPrincipalRoundTrip(t *testing.T) {
	t.Parallel()

	if _, ok := auth.FromContext(context.Background()); ok {
		t.Error("FromContext(empty) found a principal")
	}

	want := auth.Principal{UserID: 7, IsAdmin: true, Family: "f"}

	got, ok := auth.FromContext(auth.WithPrincipal(context.Background(), want))
	if !ok || got != want {
		t.Errorf("FromContext() = %+v, %v; want %+v", got, ok, want)
	}
}

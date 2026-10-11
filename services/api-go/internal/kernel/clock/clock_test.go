package clock_test

import (
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
)

func TestSystemIsUTC(t *testing.T) {
	t.Parallel()

	if loc := (clock.System{}).Now().Location(); loc != time.UTC {
		t.Errorf("System.Now() location = %v, want UTC", loc)
	}
}

func TestFakeAdvances(t *testing.T) {
	t.Parallel()

	start := time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC)
	fake := clock.NewFake(start)
	fake.Advance(90 * time.Second)

	if got, want := fake.Now(), start.Add(90*time.Second); !got.Equal(want) {
		t.Errorf("Now() = %v, want %v", got, want)
	}
}

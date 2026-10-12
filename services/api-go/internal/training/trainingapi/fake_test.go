package trainingapi_test

import (
	"errors"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/trainingapi"
)

func TestFake(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	f := &trainingapi.Fake{
		Workouts: map[int64][]trainingapi.WorkoutSummary{7: {{ID: 3, Status: "completed"}, {ID: 2, Status: "in_progress"}, {ID: 1}}},
		Counted:  trainingapi.Counts{Completed: 4, CompletedSince: 1},
	}

	if got, err := f.RecentWorkouts(ctx, 7, 2); err != nil || len(got) != 2 || got[0].ID != 3 {
		t.Errorf("RecentWorkouts = %v, %v", got, err)
	}

	if got, ok, err := f.InProgress(ctx, 7); err != nil || !ok || got.ID != 2 {
		t.Errorf("InProgress = %v, %v, %v", got, ok, err)
	}

	if _, ok, _ := f.InProgress(ctx, 8); ok {
		t.Error("InProgress found a workout for a user without any")
	}

	if got, err := f.Counts(ctx, 7, time.Now()); err != nil || got.Completed != 4 {
		t.Errorf("Counts = %v, %v", got, err)
	}

	f.Err = errors.New("boom")

	if _, err := f.RecentWorkouts(ctx, 7, 1); err == nil {
		t.Error("RecentWorkouts ignored Err")
	}

	if _, _, err := f.InProgress(ctx, 7); err == nil {
		t.Error("InProgress ignored Err")
	}

	if _, err := f.Counts(ctx, 7, time.Now()); err == nil {
		t.Error("Counts ignored Err")
	}
}

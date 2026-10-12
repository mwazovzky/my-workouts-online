package trainingapi

import (
	"context"
	"time"
)

// Fake is an in-memory Training for other modules' tests: Workouts are
// returned as set (most recent first) and Counts as set.
type Fake struct {
	// Workouts are keyed by user id, most recently updated first.
	Workouts map[int64][]WorkoutSummary
	Counted  Counts
	// Err, when set, is returned by every method.
	Err error
}

var _ Training = (*Fake)(nil)

// RecentWorkouts implements Training.
func (f *Fake) RecentWorkouts(_ context.Context, userID int64, n int) ([]WorkoutSummary, error) {
	if f.Err != nil {
		return nil, f.Err
	}

	workouts := f.Workouts[userID]

	return workouts[:min(n, len(workouts))], nil
}

// InProgress implements Training.
func (f *Fake) InProgress(_ context.Context, userID int64) (WorkoutSummary, bool, error) {
	if f.Err != nil {
		return WorkoutSummary{}, false, f.Err
	}

	for _, w := range f.Workouts[userID] {
		if w.Status == "in_progress" {
			return w, true, nil
		}
	}

	return WorkoutSummary{}, false, nil
}

// Counts implements Training.
func (f *Fake) Counts(context.Context, int64, time.Time) (Counts, error) {
	if f.Err != nil {
		return Counts{}, f.Err
	}

	return f.Counted, nil
}

// Package trainingapi is what other modules may know about workouts: the
// dashboard shows the in-progress workout, the recent ones and the
// completion counts. Plain DTOs, no training domain types (ADR 0001). The
// training app.Service implements Training; Fake stands in for it in other
// modules' tests.
package trainingapi

import (
	"context"
	"time"
)

// Training is the training module's read surface for other modules.
type Training interface {
	// RecentWorkouts returns the user's n most recently updated workouts.
	RecentWorkouts(ctx context.Context, userID int64, n int) ([]WorkoutSummary, error)
	// InProgress returns the user's most recently updated unfinished
	// workout; false when there is none.
	InProgress(ctx context.Context, userID int64) (WorkoutSummary, bool, error)
	// Counts returns how many workouts the user completed, in all and since
	// the given time.
	Counts(ctx context.Context, userID int64, since time.Time) (Counts, error)
}

// WorkoutSummary is a workout as a list shows it: no activities, their
// count instead. Status is the contract's value (in_progress, completed).
type WorkoutSummary struct {
	ID              int64
	UserID          int64
	TemplateID      *int64
	Name            string
	Status          string
	ActivitiesCount int
	CreatedAt       time.Time
	UpdatedAt       time.Time
}

// Counts are a user's completed workouts.
type Counts struct {
	Completed      int
	CompletedSince int
}

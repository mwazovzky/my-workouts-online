// Package domain holds the training module's types: a user's workouts with
// their activities and sets, and the rules a workout's life follows.
package domain

import (
	"errors"
	"fmt"
	"strings"
	"time"
)

// Status is where a workout is in its life.
type Status string

// The statuses.
const (
	InProgress Status = "in_progress"
	Completed  Status = "completed"
)

// The invariants' failures. Use cases turn them into apperr errors with the
// contract's codes.
var (
	// ErrNotInProgress is a change to a completed workout, or a repeat of an
	// unfinished one.
	ErrNotInProgress = errors.New("the workout is completed")
	// ErrNotCompleted is a repeat of a workout that is still in progress.
	ErrNotCompleted = errors.New("the workout is not completed")
	// ErrCompletedSetRequiresEffort is a set marked completed with no effort.
	ErrCompletedSetRequiresEffort = errors.New("a completed set needs effort")
)

// Workout is one user's training session, copied from a template or from
// another workout. Version counts its changes; it is the ETag.
type Workout struct {
	ID     int64
	UserID int64
	// TemplateID is nil for a repeat, or when the template was deleted.
	TemplateID *int64
	Name       string
	Status     Status
	Version    int
	CreatedAt  time.Time
	UpdatedAt  time.Time
	// ActivitiesCount is set only by the queries that count; the list.
	ActivitiesCount *int
	// Activities are in order; nil when they were not loaded.
	Activities []Activity
}

// Activity is one exercise of a workout.
type Activity struct {
	ID         int64
	ExerciseID int64
	Order      int
	Sets       []Set
}

// Set is one set of an activity. DifficultyValue carries no unit: the
// exercise's equipment says what it measures.
type Set struct {
	ID              int64
	Order           int
	EffortValue     int
	DifficultyValue *float64
	IsCompleted     bool
}

// CheckCompletion returns ErrCompletedSetRequiresEffort for a completed set
// with no effort: a set cannot be done with zero repetitions or seconds.
func (s Set) CheckCompletion() error {
	if s.IsCompleted && s.EffortValue <= 0 {
		return ErrCompletedSetRequiresEffort
	}

	return nil
}

// CanChange returns ErrNotInProgress for a completed workout: its activities
// are history.
func (w Workout) CanChange() error {
	if w.Status != InProgress {
		return ErrNotInProgress
	}

	return nil
}

// Complete marks an in-progress workout completed and counts the change.
func (w *Workout) Complete(now time.Time) error {
	if err := w.CanChange(); err != nil {
		return err
	}

	w.Status = Completed
	w.Version++
	w.UpdatedAt = now

	return nil
}

// Repeat returns a new in-progress copy of a completed workout: same name,
// activities and sets, every set to do again, and no template link.
func (w Workout) Repeat(now time.Time) (Workout, error) {
	if w.Status != Completed {
		return Workout{}, ErrNotCompleted
	}

	copied := Workout{
		UserID: w.UserID, Name: w.Name, Status: InProgress, Version: 1, CreatedAt: now, UpdatedAt: now,
		Activities: make([]Activity, len(w.Activities)),
	}

	for i, a := range w.Activities {
		sets := make([]Set, len(a.Sets))
		for j, s := range a.Sets {
			sets[j] = Set{Order: s.Order, EffortValue: s.EffortValue, DifficultyValue: s.DifficultyValue}
		}

		copied.Activities[i] = Activity{ExerciseID: a.ExerciseID, Order: a.Order, Sets: sets}
	}

	return copied, nil
}

// ETag is the workout's entity tag, "<id>-<version>", as PHP's WorkoutETag.
func (w Workout) ETag() string {
	return fmt.Sprintf(`"%d-%d"`, w.ID, w.Version)
}

// Matches reports whether an If-Match field value (`*`, or a comma-separated
// list of tags) matches the workout. Comparison is strong (RFC 9110): a weak
// tag (W/"…") never matches.
func (w Workout) Matches(ifMatch string) bool {
	ifMatch = strings.TrimSpace(ifMatch)
	if ifMatch == "*" {
		return true
	}

	current := w.ETag()

	for tag := range strings.SplitSeq(ifMatch, ",") {
		if strings.TrimSpace(tag) == current {
			return true
		}
	}

	return false
}

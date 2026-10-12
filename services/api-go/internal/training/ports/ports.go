// Package ports declares what the training module's use cases need from the
// outside: its own repository, and the catalog through catalogapi.
package ports

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/catalogapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/pagination"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/domain"
)

// Workouts stores workouts with their activities and sets. Every call joins
// the transaction in ctx.
type Workouts interface {
	// Create inserts the workout with its activities and sets and returns it
	// with their ids.
	Create(ctx context.Context, w domain.Workout) (domain.Workout, error)
	// Owned returns the user's workout, without activities; apperr NotFound
	// when it does not exist or belongs to someone else.
	Owned(ctx context.Context, userID, id int64) (domain.Workout, error)
	// Lock returns the workout, without activities, and locks it until the
	// transaction ends.
	Lock(ctx context.Context, id int64) (domain.Workout, error)
	// Activities returns the workout's activities with their sets, in order.
	Activities(ctx context.Context, workoutID int64) ([]domain.Activity, error)
	// List returns a page of the user's workouts, most recently updated
	// first, with ActivitiesCount and without activities.
	List(ctx context.Context, userID int64, page pagination.Page) (pagination.Result[domain.Workout], error)
	// ReplaceActivities makes activities the workout's activities: rows not
	// listed are deleted, listed ids are updated, zero ids are created. The
	// caller has checked that every id belongs to the workout.
	ReplaceActivities(ctx context.Context, workoutID int64, activities []domain.Activity, now time.Time) error
	// SaveHeader stores the workout's status, version and updated time.
	SaveHeader(ctx context.Context, w domain.Workout) error
	// Delete removes the workout; its activities and sets go with it.
	Delete(ctx context.Context, id int64) error

	// InProgress returns the user's most recently updated unfinished
	// workout, with ActivitiesCount; false when there is none.
	InProgress(ctx context.Context, userID int64) (domain.Workout, bool, error)
	// CountCompleted returns how many workouts the user completed, in all
	// and since the given time.
	CountCompleted(ctx context.Context, userID int64, since time.Time) (all, recent int, err error)
}

// Catalog is what training reads from the catalog module; catalogapi.Catalog
// satisfies it.
type Catalog interface {
	ExerciseLookup(ctx context.Context, ids []int64, locale i18n.Locale) (map[int64]catalogapi.ExerciseInfo, error)
	TemplateSnapshot(ctx context.Context, id int64, locale i18n.Locale) (catalogapi.Snapshot, error)
	TemplateHeaders(ctx context.Context, ids []int64, locale i18n.Locale) (map[int64]catalogapi.TemplateHeader, error)
}

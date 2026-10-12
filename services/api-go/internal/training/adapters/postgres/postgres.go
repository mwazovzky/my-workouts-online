// Package postgres is the training module's Postgres adapter: the Workouts
// repository over the sqlc queries in sql/training (generated into sqlcgen).
// Every call takes its connection from the Transactor, so it joins the
// transaction a use case opened.
package postgres

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/pagination"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/ports"
)

// Workouts implements ports.Workouts.
type Workouts struct {
	tx *postgres.Transactor
}

var _ ports.Workouts = (*Workouts)(nil)

// NewWorkouts returns the Workouts repository.
func NewWorkouts(tx *postgres.Transactor) *Workouts {
	return &Workouts{tx: tx}
}

func (r *Workouts) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(r.tx.Querier(ctx))
}

// Create implements ports.Workouts.
func (r *Workouts) Create(ctx context.Context, w domain.Workout) (domain.Workout, error) {
	q := r.q(ctx)

	row, err := q.CreateWorkout(ctx, sqlcgen.CreateWorkoutParams{
		UserID: w.UserID, TemplateID: w.TemplateID, Name: w.Name, Status: string(w.Status), Now: w.CreatedAt,
	})
	if err != nil {
		return domain.Workout{}, postgres.MapError(err)
	}

	created := toWorkout(row)
	created.Activities = make([]domain.Activity, len(w.Activities))

	for i, a := range w.Activities {
		if created.Activities[i], err = r.insertActivity(ctx, created.ID, a, w.CreatedAt); err != nil {
			return domain.Workout{}, err
		}
	}

	return created, nil
}

func (r *Workouts) insertActivity(ctx context.Context, workoutID int64, a domain.Activity, now time.Time) (domain.Activity, error) {
	q := r.q(ctx)

	id, err := q.InsertWorkoutActivity(ctx, sqlcgen.InsertWorkoutActivityParams{
		WorkoutID: workoutID, ExerciseID: a.ExerciseID, Position: int32(a.Order), Now: now, //nolint:gosec // orders are 1..1000
	})
	if err != nil {
		return domain.Activity{}, postgres.MapError(err)
	}

	a.ID = id
	sets := make([]domain.Set, len(a.Sets))

	for j, s := range a.Sets {
		if s.ID, err = r.insertSet(ctx, id, s, now); err != nil {
			return domain.Activity{}, err
		}

		sets[j] = s
	}

	a.Sets = sets

	return a, nil
}

func (r *Workouts) insertSet(ctx context.Context, activityID int64, s domain.Set, now time.Time) (int64, error) {
	id, err := r.q(ctx).InsertWorkoutSet(ctx, sqlcgen.InsertWorkoutSetParams{
		ActivityID: activityID, Position: int32(s.Order), EffortValue: int32(s.EffortValue), //nolint:gosec // validated to the contract's bounds
		DifficultyValue: s.DifficultyValue, IsCompleted: s.IsCompleted, Now: now,
	})

	return id, postgres.MapError(err)
}

// Owned implements ports.Workouts.
func (r *Workouts) Owned(ctx context.Context, userID, id int64) (domain.Workout, error) {
	row, err := r.q(ctx).GetOwnedWorkout(ctx, id, userID)
	if err != nil {
		return domain.Workout{}, postgres.MapError(err)
	}

	return toWorkout(row), nil
}

// Lock implements ports.Workouts.
func (r *Workouts) Lock(ctx context.Context, id int64) (domain.Workout, error) {
	row, err := r.q(ctx).LockWorkout(ctx, id)
	if err != nil {
		return domain.Workout{}, postgres.MapError(err)
	}

	return toWorkout(row), nil
}

// Activities implements ports.Workouts: two queries whatever the size.
func (r *Workouts) Activities(ctx context.Context, workoutID int64) ([]domain.Activity, error) {
	q := r.q(ctx)

	rows, err := q.ListWorkoutActivities(ctx, workoutID)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	sets, err := q.ListWorkoutSets(ctx, workoutID)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	byActivity := map[int64][]domain.Set{}
	for _, s := range sets {
		byActivity[s.ActivityID] = append(byActivity[s.ActivityID], domain.Set{
			ID: s.ID, Order: int(s.Order), EffortValue: int(s.EffortValue), DifficultyValue: s.DifficultyValue, IsCompleted: s.IsCompleted,
		})
	}

	activities := make([]domain.Activity, len(rows))
	for i, a := range rows {
		activities[i] = domain.Activity{ID: a.ID, ExerciseID: a.ExerciseID, Order: int(a.Order), Sets: byActivity[a.ID]}
		if activities[i].Sets == nil {
			activities[i].Sets = []domain.Set{}
		}
	}

	return activities, nil
}

// List implements ports.Workouts.
func (r *Workouts) List(ctx context.Context, userID int64, page pagination.Page) (pagination.Result[domain.Workout], error) {
	q := r.q(ctx)

	total, err := q.CountOwnedWorkouts(ctx, userID)
	if err != nil {
		return pagination.Result[domain.Workout]{}, postgres.MapError(err)
	}

	rows, err := q.ListOwnedWorkouts(ctx, sqlcgen.ListOwnedWorkoutsParams{
		UserID: userID, PageSize: int32(page.Size), PageOffset: int64(page.Offset()), //nolint:gosec // the contract bounds per_page to 100
	})
	if err != nil {
		return pagination.Result[domain.Workout]{}, postgres.MapError(err)
	}

	items := make([]domain.Workout, len(rows))
	for i, row := range rows {
		count := int(row.ActivitiesCount)
		items[i] = toWorkout(sqlcgen.Workout{
			ID: row.ID, UserID: row.UserID, TemplateID: row.TemplateID, Name: row.Name, Status: row.Status,
			Version: row.Version, CreatedAt: row.CreatedAt, UpdatedAt: row.UpdatedAt,
		})
		items[i].ActivitiesCount = &count
	}

	return pagination.Result[domain.Workout]{Items: items, Total: int(total), Page: page}, nil
}

// ReplaceActivities implements ports.Workouts. Kept activities and sets are
// first parked at order -id, so the new orders can be written in any
// sequence without tripping the (parent, order) unique keys.
func (r *Workouts) ReplaceActivities(ctx context.Context, workoutID int64, activities []domain.Activity, now time.Time) error {
	q := r.q(ctx)

	var keep []int64
	for _, a := range activities {
		if a.ID != 0 {
			keep = append(keep, a.ID)
		}
	}

	if err := q.DeleteWorkoutActivitiesExcept(ctx, workoutID, nonNil(keep)); err != nil {
		return postgres.MapError(err)
	}

	if err := q.ParkWorkoutActivities(ctx, workoutID, nonNil(keep)); err != nil {
		return postgres.MapError(err)
	}

	for _, a := range activities {
		if a.ID == 0 {
			if _, err := r.insertActivity(ctx, workoutID, a, now); err != nil {
				return err
			}

			continue
		}

		if err := q.UpdateWorkoutActivity(ctx, sqlcgen.UpdateWorkoutActivityParams{
			ID: a.ID, ExerciseID: a.ExerciseID, Position: int32(a.Order), Now: now, //nolint:gosec // orders are 1..1000
		}); err != nil {
			return postgres.MapError(err)
		}

		if err := r.replaceSets(ctx, a, now); err != nil {
			return err
		}
	}

	return nil
}

func (r *Workouts) replaceSets(ctx context.Context, a domain.Activity, now time.Time) error {
	q := r.q(ctx)

	var keep []int64
	for _, s := range a.Sets {
		if s.ID != 0 {
			keep = append(keep, s.ID)
		}
	}

	if err := q.DeleteWorkoutSetsExcept(ctx, a.ID, nonNil(keep)); err != nil {
		return postgres.MapError(err)
	}

	if err := q.ParkWorkoutSets(ctx, a.ID, nonNil(keep)); err != nil {
		return postgres.MapError(err)
	}

	for _, s := range a.Sets {
		if s.ID == 0 {
			if _, err := r.insertSet(ctx, a.ID, s, now); err != nil {
				return err
			}

			continue
		}

		if err := q.UpdateWorkoutSet(ctx, sqlcgen.UpdateWorkoutSetParams{
			ID: s.ID, Position: int32(s.Order), EffortValue: int32(s.EffortValue), //nolint:gosec // validated to the contract's bounds
			DifficultyValue: s.DifficultyValue, IsCompleted: s.IsCompleted, Now: now,
		}); err != nil {
			return postgres.MapError(err)
		}
	}

	return nil
}

// SaveHeader implements ports.Workouts.
func (r *Workouts) SaveHeader(ctx context.Context, w domain.Workout) error {
	return postgres.MapError(r.q(ctx).UpdateWorkoutStatus(ctx, sqlcgen.UpdateWorkoutStatusParams{
		ID: w.ID, Status: string(w.Status), Version: int32(w.Version), Now: w.UpdatedAt, //nolint:gosec // versions stay small
	}))
}

// Delete implements ports.Workouts.
func (r *Workouts) Delete(ctx context.Context, id int64) error {
	return postgres.MapError(r.q(ctx).DeleteWorkout(ctx, id))
}

// InProgress implements ports.Workouts.
func (r *Workouts) InProgress(ctx context.Context, userID int64) (domain.Workout, bool, error) {
	row, err := r.q(ctx).InProgressWorkout(ctx, userID)
	if err != nil {
		err = postgres.MapError(err)
		if apperr.HasKind(err, apperr.KindNotFound) {
			return domain.Workout{}, false, nil
		}

		return domain.Workout{}, false, err
	}

	count := int(row.ActivitiesCount)
	w := toWorkout(sqlcgen.Workout{
		ID: row.ID, UserID: row.UserID, TemplateID: row.TemplateID, Name: row.Name, Status: row.Status,
		Version: row.Version, CreatedAt: row.CreatedAt, UpdatedAt: row.UpdatedAt,
	})
	w.ActivitiesCount = &count

	return w, true, nil
}

// CountCompleted implements ports.Workouts.
func (r *Workouts) CountCompleted(ctx context.Context, userID int64, since time.Time) (int, int, error) {
	row, err := r.q(ctx).CountCompletedWorkouts(ctx, since, userID)
	if err != nil {
		return 0, 0, postgres.MapError(err)
	}

	return int(row.Completed), int(row.CompletedSince), nil
}

func toWorkout(row sqlcgen.Workout) domain.Workout {
	return domain.Workout{
		ID: row.ID, UserID: row.UserID, TemplateID: row.TemplateID, Name: row.Name, Status: domain.Status(row.Status),
		Version: int(row.Version), CreatedAt: row.CreatedAt, UpdatedAt: row.UpdatedAt,
	}
}

// nonNil keeps an empty id list from being sent as SQL NULL, which `= ANY`
// would treat as unknown rather than as no ids.
func nonNil(ids []int64) []int64 {
	if ids == nil {
		return []int64{}
	}

	return ids
}

package app

import (
	"context"
	"math"
	"strconv"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/catalogapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/validate"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/domain"
)

// ActivityInput is one activity of a replace; a nil ID creates it.
type ActivityInput struct {
	ID         *int64
	ExerciseID int64
	Order      int
	Sets       []SetInput
}

// SetInput is one set of a replace; a nil ID creates it.
type SetInput struct {
	ID              *int64
	Order           int
	EffortValue     int
	DifficultyValue *float64
	IsCompleted     bool
}

// heartRateZone is the unit whose difficulty is a zone from 1 to 5.
const heartRateZone = "heart_rate_zone"

// ReplaceActivities makes in the caller's in-progress workout's activities
// and sets, as the workout editor saves them. Checks, in order: someone
// else's workout is a 404; a completed one a 403; no If-Match a 428; then,
// under the workout's row lock, an If-Match that does not match the current
// ETag a 412, and the payload's business rules a 422. The write bumps the
// workout's version, so the response carries a new ETag.
func (s *Service) ReplaceActivities(ctx context.Context, p auth.Principal, id int64, ifMatch *string,
	in []ActivityInput, locale i18n.Locale,
) (View, error) {
	w, err := s.Workouts.Owned(ctx, p.UserID, id)
	if err != nil {
		return View{}, err
	}

	if w.CanChange() != nil {
		return View{}, errForbidden()
	}

	if ifMatch == nil {
		return View{}, apperr.PreconditionRequired("Send the workout's ETag in If-Match.")
	}

	var view View

	err = s.Tx.InTx(ctx, func(ctx context.Context) error {
		current, err := s.Workouts.Lock(ctx, id)
		if err != nil {
			return err
		}

		if current.CanChange() != nil {
			return errForbidden()
		}

		if !current.Matches(*ifMatch) {
			return apperr.PreconditionFailed("This workout was changed elsewhere. Reload it to continue.")
		}

		existing, err := s.Workouts.Activities(ctx, id)
		if err != nil {
			return err
		}

		exercises, err := s.Catalog.ExerciseLookup(ctx, inputExerciseIDs(in), locale)
		if err != nil {
			return err
		}

		activities, err := checkReplace(in, existing, exercises)
		if err != nil {
			return err
		}

		now := s.now()
		if err := s.Workouts.ReplaceActivities(ctx, id, activities, now); err != nil {
			return err
		}

		current.Version++
		current.UpdatedAt = now

		if err := s.Workouts.SaveHeader(ctx, current); err != nil {
			return err
		}

		view, err = s.withActivities(ctx, current, locale)

		return err
	})
	if err != nil {
		return View{}, err
	}

	return view, nil
}

func inputExerciseIDs(in []ActivityInput) []int64 {
	activities := make([]domain.Activity, len(in))
	for i, a := range in {
		activities[i] = domain.Activity{ExerciseID: a.ExerciseID}
	}

	return exerciseIDs(activities)
}

// checkReplace validates a replace against the workout's current
// activities and returns it as domain activities (zero ids create). Every
// failure is reported, with a pointer into the payload:
//   - an activity id that is not the workout's, or a set id that is not its
//     activity's, is not_found; ids and activity orders must not repeat
//     (not_unique), nor set orders within an activity;
//   - an exercise must exist, and a retired one may only stay on the
//     activity that already has it (ADR 0010);
//   - a completed set needs effort; a heart-rate zone is a whole number
//     from 1 to 5.
func checkReplace(in []ActivityInput, existing []domain.Activity, exercises map[int64]catalogapi.ExerciseInfo) ([]domain.Activity, error) {
	current := map[int64]domain.Activity{}
	for _, a := range existing {
		current[a.ID] = a
	}

	activityIDs, orders, setIDs := counts(in)

	v := validate.New()
	out := make([]domain.Activity, len(in))

	for i, a := range in {
		v.Nested("activities", i, func(v *validate.Validator) {
			own, isOwn := domain.Activity{}, false

			if a.ID != nil {
				own, isOwn = current[*a.ID]
				name := "activities." + strconv.Itoa(i) + ".id"

				if activityIDs[*a.ID] > 1 {
					v.Fail("id", apperr.FieldNotUnique, "validation.distinct", map[string]string{"attribute": name})
				}

				v.Check(isOwn, "id", apperr.FieldNotFound, "validation.exists", map[string]string{"attribute": name})
			}

			v.Check(orders[a.Order] == 1, "order", apperr.FieldNotUnique, "validation.distinct", map[string]string{"attribute": "activity order"})

			exercise, known := exercises[a.ExerciseID]
			keepsRetired := isOwn && own.ExerciseID == a.ExerciseID
			v.Check(known && (!exercise.Retired || keepsRetired), "exercise_id", apperr.FieldNotFound, "validation.exists",
				map[string]string{"attribute": "exercise"})

			out[i] = domain.Activity{ExerciseID: a.ExerciseID, Order: a.Order, Sets: checkSets(v, i, a, own, isOwn, setIDs, exercise.DifficultyUnit)}
			if isOwn {
				out[i].ID = own.ID
			}
		})
	}

	return out, v.Err()
}

func checkSets(v *validate.Validator, i int, a ActivityInput, own domain.Activity, isOwn bool, setIDs map[int64]int, unit string) []domain.Set {
	ownSets := map[int64]bool{}
	if isOwn {
		for _, s := range own.Sets {
			ownSets[s.ID] = true
		}
	}

	orders := map[int]int{}
	for _, s := range a.Sets {
		orders[s.Order]++
	}

	sets := make([]domain.Set, len(a.Sets))

	for j, s := range a.Sets {
		v.Nested("sets", j, func(v *validate.Validator) {
			set := domain.Set{Order: s.Order, EffortValue: s.EffortValue, DifficultyValue: s.DifficultyValue, IsCompleted: s.IsCompleted}

			if s.ID != nil {
				name := "activities." + strconv.Itoa(i) + ".sets." + strconv.Itoa(j) + ".id"

				if setIDs[*s.ID] > 1 {
					v.Fail("id", apperr.FieldNotUnique, "validation.distinct", map[string]string{"attribute": name})
				}

				if v.Check(ownSets[*s.ID], "id", apperr.FieldNotFound, "validation.exists", map[string]string{"attribute": name}) {
					set.ID = *s.ID
				}
			}

			v.Check(orders[s.Order] == 1, "order", apperr.FieldNotUnique, "validation.distinct", map[string]string{"attribute": "set order"})

			v.Check(set.CheckCompletion() == nil, "is_completed", apperr.FieldInvalidValue,
				"A set cannot be marked as completed with 0 effort.", map[string]string{"attribute": "completion status"})

			if unit == heartRateZone && s.DifficultyValue != nil {
				zone := *s.DifficultyValue
				v.Check(zone == math.Trunc(zone) && zone >= 1 && zone <= 5, "difficulty_value", apperr.FieldOutOfRange,
					"Heart-rate zone must be between 1 and 5.", map[string]string{"attribute": "difficulty value"})
			}

			sets[j] = set
		})
	}

	return sets
}

// counts tallies the payload's activity ids, activity orders and set ids,
// to find repeats.
func counts(in []ActivityInput) (map[int64]int, map[int]int, map[int64]int) {
	activityIDs, orders, setIDs := map[int64]int{}, map[int]int{}, map[int64]int{}

	for _, a := range in {
		if a.ID != nil {
			activityIDs[*a.ID]++
		}

		orders[a.Order]++

		for _, s := range a.Sets {
			if s.ID != nil {
				setIDs[*s.ID]++
			}
		}
	}

	return activityIDs, orders, setIDs
}

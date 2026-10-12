// Package app holds the training module's use cases. Service is the module's
// only entry point: the HTTP adapter calls its use cases, and the dashboard
// reaches it through trainingapi.Training, which it implements.
package app

import (
	"context"
	"log/slog"
	"maps"
	"slices"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/catalogapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/pagination"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/txn"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/ports"
)

// The workout list's page sizes, as in the PHP WorkoutIndexRequest.
const (
	DefaultPageSize = 20
	MaxPageSize     = 100
)

// Deps are the Service's collaborators.
type Deps struct {
	Workouts ports.Workouts
	Catalog  ports.Catalog
	Tx       txn.Transactor
	Clock    clock.Clock
	Logger   *slog.Logger
}

// Service runs the training module's use cases.
type Service struct {
	Deps
}

// New returns a Service.
func New(deps Deps) *Service {
	return &Service{Deps: deps}
}

// The catalog data a View shows, as catalogapi gives it; re-exported so the
// HTTP adapter depends on this module only.
type (
	// TemplateHeader is a template's localized name and description.
	TemplateHeader = catalogapi.TemplateHeader
	// Snapshot is a template with its activities and sets.
	Snapshot = catalogapi.Snapshot
	// ExerciseInfo is an exercise as an activity shows it.
	ExerciseInfo = catalogapi.ExerciseInfo
)

// View is a workout with what its response shows beside it. Template is the
// template's header when it was asked for and still exists; Exercises
// resolve the activities' exercises; Snapshot is the template a workout was
// just created from.
type View struct {
	Workout   domain.Workout
	Template  *catalogapi.TemplateHeader
	Exercises map[int64]catalogapi.ExerciseInfo
	Snapshot  *catalogapi.Snapshot
}

// errForbidden is the 403 of an action the workout's status does not allow.
func errForbidden() error {
	return apperr.Forbidden(apperr.CodeForbidden, "This action is unauthorized.")
}

// now is the clock to the second, as the PHP API stores timestamps.
func (s *Service) now() time.Time {
	return s.Clock.Now().UTC().Truncate(time.Second)
}

// List returns a page of the caller's workouts, most recently updated first,
// with their templates' headers by template id.
func (s *Service) List(ctx context.Context, p auth.Principal, page pagination.Page, locale i18n.Locale) (
	pagination.Result[domain.Workout], map[int64]catalogapi.TemplateHeader, error,
) {
	result, err := s.Workouts.List(ctx, p.UserID, page)
	if err != nil {
		return result, nil, err
	}

	templates, err := s.templates(ctx, result.Items, locale)

	return result, templates, err
}

func (s *Service) templates(ctx context.Context, workouts []domain.Workout, locale i18n.Locale) (map[int64]catalogapi.TemplateHeader, error) {
	ids := map[int64]bool{}
	for _, w := range workouts {
		if w.TemplateID != nil {
			ids[*w.TemplateID] = true
		}
	}

	if len(ids) == 0 {
		return map[int64]catalogapi.TemplateHeader{}, nil
	}

	return s.Catalog.TemplateHeaders(ctx, slices.Sorted(maps.Keys(ids)), locale)
}

// Create starts a workout from a template: its activities and sets copied,
// nothing completed, and its name in the request's locale, kept as is
// afterwards. An unknown template is a 422 on workout_template_id.
func (s *Service) Create(ctx context.Context, p auth.Principal, templateID int64, locale i18n.Locale) (View, error) {
	snapshot, err := s.Catalog.TemplateSnapshot(ctx, templateID, locale)
	if apperr.HasKind(err, apperr.KindNotFound) {
		return View{}, apperr.Validation(apperr.FieldError{
			Pointer: "/workout_template_id", Code: apperr.FieldNotFound,
			Detail: "The selected workout template could not be found.", Args: map[string]string{"attribute": "workout template"},
		})
	}

	if err != nil {
		return View{}, err
	}

	now := s.now()
	w := domain.Workout{
		UserID: p.UserID, TemplateID: &snapshot.ID, Name: snapshot.Name, Status: domain.InProgress, Version: 1,
		CreatedAt: now, UpdatedAt: now, Activities: make([]domain.Activity, len(snapshot.Activities)),
	}

	for i, a := range snapshot.Activities {
		sets := make([]domain.Set, len(a.Sets))
		for j, set := range a.Sets {
			sets[j] = domain.Set{Order: set.Order, EffortValue: set.EffortValue, DifficultyValue: set.DifficultyValue}
		}

		w.Activities[i] = domain.Activity{ExerciseID: a.ExerciseID, Order: a.Order, Sets: sets}
	}

	var created domain.Workout

	err = s.Tx.InTx(ctx, func(ctx context.Context) error {
		created, err = s.Workouts.Create(ctx, w)

		return err
	})
	if err != nil {
		return View{}, err
	}

	s.Logger.InfoContext(ctx, "workout.started", "user_id", p.UserID, "workout_id", created.ID, "template_id", templateID)

	// The response shows the template the workout came from, as PHP's does,
	// and not the copied activities.
	created.Activities = nil

	return View{Workout: created, Snapshot: &snapshot}, nil
}

// Get returns the caller's workout with its activities, their exercises and
// its template's header. Someone else's workout is not found, exactly as a
// missing one.
func (s *Service) Get(ctx context.Context, p auth.Principal, id int64, locale i18n.Locale) (View, error) {
	w, err := s.Workouts.Owned(ctx, p.UserID, id)
	if err != nil {
		return View{}, err
	}

	view, err := s.withActivities(ctx, w, locale)
	if err != nil {
		return View{}, err
	}

	count := len(view.Workout.Activities)
	view.Workout.ActivitiesCount = &count

	templates, err := s.templates(ctx, []domain.Workout{w}, locale)
	if err != nil {
		return View{}, err
	}

	if w.TemplateID != nil {
		if header, ok := templates[*w.TemplateID]; ok {
			view.Template = &header
		}
	}

	return view, nil
}

// withActivities loads the workout's activities and their exercises.
func (s *Service) withActivities(ctx context.Context, w domain.Workout, locale i18n.Locale) (View, error) {
	activities, err := s.Workouts.Activities(ctx, w.ID)
	if err != nil {
		return View{}, err
	}

	w.Activities = activities

	exercises, err := s.Catalog.ExerciseLookup(ctx, exerciseIDs(activities), locale)
	if err != nil {
		return View{}, err
	}

	return View{Workout: w, Exercises: exercises}, nil
}

func exerciseIDs(activities []domain.Activity) []int64 {
	ids := map[int64]bool{}
	for _, a := range activities {
		ids[a.ExerciseID] = true
	}

	return slices.Sorted(maps.Keys(ids))
}

// Complete marks the caller's in-progress workout completed. A completed
// workout is a 403.
func (s *Service) Complete(ctx context.Context, p auth.Principal, id int64) (View, error) {
	if _, err := s.Workouts.Owned(ctx, p.UserID, id); err != nil {
		return View{}, err
	}

	var w domain.Workout

	err := s.Tx.InTx(ctx, func(ctx context.Context) error {
		var err error
		if w, err = s.Workouts.Lock(ctx, id); err != nil {
			return err
		}

		if err := w.Complete(s.now()); err != nil {
			return errForbidden()
		}

		return s.Workouts.SaveHeader(ctx, w)
	})
	if err != nil {
		return View{}, err
	}

	s.Logger.InfoContext(ctx, "workout.completed", "user_id", p.UserID, "workout_id", id)

	return View{Workout: w}, nil
}

// Repeat starts a new in-progress copy of the caller's completed workout. An
// unfinished workout is a 403.
func (s *Service) Repeat(ctx context.Context, p auth.Principal, id int64) (View, error) {
	source, err := s.Workouts.Owned(ctx, p.UserID, id)
	if err != nil {
		return View{}, err
	}

	if source.Status != domain.Completed {
		return View{}, errForbidden()
	}

	if source.Activities, err = s.Workouts.Activities(ctx, id); err != nil {
		return View{}, err
	}

	copied, err := source.Repeat(s.now())
	if err != nil {
		return View{}, errForbidden()
	}

	var created domain.Workout

	err = s.Tx.InTx(ctx, func(ctx context.Context) error {
		created, err = s.Workouts.Create(ctx, copied)

		return err
	})
	if err != nil {
		return View{}, err
	}

	s.Logger.InfoContext(ctx, "workout.repeated", "user_id", p.UserID, "source_id", id, "new_id", created.ID)

	created.Activities = nil

	return View{Workout: created}, nil
}

// Delete removes the caller's workout with its activities and sets.
func (s *Service) Delete(ctx context.Context, p auth.Principal, id int64) error {
	if _, err := s.Workouts.Owned(ctx, p.UserID, id); err != nil {
		return err
	}

	if err := s.Workouts.Delete(ctx, id); err != nil {
		return err
	}

	s.Logger.InfoContext(ctx, "workout.deleted", "user_id", p.UserID, "workout_id", id)

	return nil
}

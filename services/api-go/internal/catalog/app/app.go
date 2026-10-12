// Package app holds the catalog's use cases. Service is the module's only
// entry point: the HTTP adapter calls its use cases, and other modules reach
// it through catalogapi.Catalog, which it implements.
package app

import (
	"context"
	"log/slog"
	"slices"
	"strings"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

// Deps are the Service's collaborators.
type Deps struct {
	Programs  ports.Programs
	Templates ports.Templates
	Exercises ports.Exercises
	Clock     clock.Clock
	Logger    *slog.Logger
}

// Service runs the catalog's use cases.
type Service struct {
	Deps
}

// New returns a Service.
func New(deps Deps) *Service {
	return &Service{Deps: deps}
}

// ListPrograms returns every program, each with whether the caller is
// enrolled.
func (s *Service) ListPrograms(ctx context.Context, p auth.Principal) ([]ports.ProgramEntry, error) {
	return s.Programs.List(ctx, p.UserID)
}

// GetProgram returns a program with its weekday assignments.
func (s *Service) GetProgram(ctx context.Context, p auth.Principal, id int64) (ports.ProgramEntry, error) {
	return s.Programs.ByID(ctx, id, p.UserID)
}

// Enroll enrolls the caller in a program. Enrolling twice is not an error.
func (s *Service) Enroll(ctx context.Context, p auth.Principal, programID int64) error {
	if err := s.mustExist(ctx, programID); err != nil {
		return err
	}

	if err := s.Programs.Enroll(ctx, programID, p.UserID, s.Clock.Now()); err != nil {
		return err
	}

	s.Logger.InfoContext(ctx, "program.enrolled", "user_id", p.UserID, "program_id", programID)

	return nil
}

// Unenroll ends the caller's enrollment in a program, if any. Their workouts
// started from the program's templates stay.
func (s *Service) Unenroll(ctx context.Context, p auth.Principal, programID int64) error {
	if err := s.mustExist(ctx, programID); err != nil {
		return err
	}

	if err := s.Programs.Unenroll(ctx, programID, p.UserID); err != nil {
		return err
	}

	s.Logger.InfoContext(ctx, "program.unenrolled", "user_id", p.UserID, "program_id", programID)

	return nil
}

func (s *Service) mustExist(ctx context.Context, programID int64) error {
	exists, err := s.Programs.Exists(ctx, programID)
	if err != nil {
		return err
	}

	if !exists {
		return apperr.NotFound(nil)
	}

	return nil
}

// GetWorkoutTemplate returns a template with its activities and sets.
func (s *Service) GetWorkoutTemplate(ctx context.Context, id int64) (domain.WorkoutTemplate, error) {
	return s.Templates.ByID(ctx, id)
}

// ListExercises returns the exercises a user may add to a workout (retired
// ones left out), sorted by their name in locale ignoring case, as PHP sorts
// them (mb_strtolower, then byte order); equal names keep id order.
func (s *Service) ListExercises(ctx context.Context, locale i18n.Locale) ([]domain.Exercise, error) {
	exercises, err := s.Exercises.ListActive(ctx)
	if err != nil {
		return nil, err
	}

	slices.SortStableFunc(exercises, func(a, b domain.Exercise) int {
		return strings.Compare(strings.ToLower(a.Name.Resolve(locale)), strings.ToLower(b.Name.Resolve(locale)))
	})

	return exercises, nil
}

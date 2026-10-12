// Package ports declares what the catalog's use cases need from the outside.
package ports

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
)

// ProgramEntry is a program as one user sees it.
type ProgramEntry struct {
	Program domain.Program
	// Enrolled reports whether the user is enrolled in the program.
	Enrolled bool
}

// ScheduledTemplate is one assignment of a program the user is enrolled in.
type ScheduledTemplate struct {
	ProgramID    int64
	ProgramName  domain.LocalizedText
	TemplateID   int64
	TemplateName domain.LocalizedText
	Weekday      domain.Weekday
}

// Programs reads programs and stores enrollments.
type Programs interface {
	// List returns every program in id order, without assignments.
	List(ctx context.Context, userID int64) ([]ProgramEntry, error)
	// ByID returns the program with its assignments in the order they were
	// made; apperr NotFound otherwise.
	ByID(ctx context.Context, id, userID int64) (ProgramEntry, error)
	// Exists reports whether the program exists.
	Exists(ctx context.Context, id int64) (bool, error)
	// Enroll enrolls the user; enrolling twice is a no-op.
	Enroll(ctx context.Context, programID, userID int64, now time.Time) error
	// Unenroll removes the user's enrollment, if any.
	Unenroll(ctx context.Context, programID, userID int64) error
	// EnrolledSchedule returns every assignment of the programs the user is
	// enrolled in, by program id and then in assignment order.
	EnrolledSchedule(ctx context.Context, userID int64) ([]ScheduledTemplate, error)
}

// Templates reads workout templates.
type Templates interface {
	// ByID returns the template with its activities and sets in order, each
	// activity's exercise resolved even when it is retired; apperr NotFound
	// otherwise.
	ByID(ctx context.Context, id int64) (domain.WorkoutTemplate, error)
}

// Exercises reads exercises with their equipment and categories.
type Exercises interface {
	// ListActive returns the exercises that are not retired, in id order.
	ListActive(ctx context.Context) ([]domain.Exercise, error)
	// ByIDs returns the exercises with these ids, retired ones included, in
	// id order; unknown ids are left out.
	ByIDs(ctx context.Context, ids []int64) ([]domain.Exercise, error)
}

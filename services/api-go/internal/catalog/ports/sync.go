package ports

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
)

// RawCatalog is the catalog data files as read, before validation. Records
// are plain YAML values (map[string]any, []any, string, int, float64, bool,
// nil), normalised as PHP's CatalogLoader does: previous_names defaults to an
// empty list, an exercise's rest_time_seconds to nil, and every template
// activity to {exercise, sets: [{effort, difficulty}]}.
type RawCatalog struct {
	Equipment        []any
	Categories       []any
	Exercises        []any
	WorkoutTemplates []any
	Programs         []any
	// Retired is retired.yaml by type name; a missing type is an empty list.
	Retired map[string]any
}

// CatalogSource reads the catalog data files.
type CatalogSource interface {
	Load() (RawCatalog, error)
}

// Kind is a catalog record type, named as in the data files and the report.
type Kind string

// The catalog record types, in dependency order: each references only
// earlier ones.
const (
	KindEquipment        Kind = "equipment"
	KindCategories       Kind = "categories"
	KindExercises        Kind = "exercises"
	KindWorkoutTemplates Kind = "workout_templates"
	KindPrograms         Kind = "programs"
)

// Kinds lists the record types in dependency order.
var Kinds = []Kind{KindEquipment, KindCategories, KindExercises, KindWorkoutTemplates, KindPrograms}

// CatalogRow is an existing row as the sync indexes it: by slug when it has
// one (a managed row), otherwise by its English name.
type CatalogRow struct {
	ID   int64
	Slug *string
	Name string
	// Retired is set for a retired exercise.
	Retired bool
}

// EquipmentRecord is an equipment row's synced fields.
type EquipmentRecord struct {
	Name           domain.LocalizedText
	DifficultyUnit domain.DifficultyUnit
}

// CategoryRecord is a category row's synced fields.
type CategoryRecord struct {
	Name domain.LocalizedText
}

// ExerciseRecord is an exercise row's synced fields. CategoryIDs are sorted.
type ExerciseRecord struct {
	Name            domain.LocalizedText
	Description     domain.LocalizedText
	EquipmentID     int64
	EffortType      domain.EffortType
	RestTimeSeconds *int
	CategoryIDs     []int64
}

// TemplateRecord is a workout template row's synced fields.
type TemplateRecord struct {
	Name        domain.LocalizedText
	Description domain.LocalizedText
	Activities  []ActivityRecord
}

// ActivityRecord is one template activity, with its sets in order.
type ActivityRecord struct {
	ExerciseID int64
	Order      int
	Sets       []SetRecord
}

// SetRecord is one planned set.
type SetRecord struct {
	Order           int
	EffortValue     int
	DifficultyValue *float64
}

// ProgramRecord is a program row's synced fields; Schedule is in assignment
// order.
type ProgramRecord struct {
	Name        domain.LocalizedText
	Description domain.LocalizedText
	Schedule    []AssignmentRecord
}

// AssignmentRecord schedules a template on a weekday.
type AssignmentRecord struct {
	TemplateID int64
	Weekday    domain.Weekday
}

// CatalogStore is what catalog sync reads and writes. Every call joins the
// transaction in ctx.
type CatalogStore interface {
	// Rows returns every row of kind in id order, retired exercises included.
	Rows(ctx context.Context, kind Kind) ([]CatalogRow, error)

	Equipment(ctx context.Context, id int64) (EquipmentRecord, error)
	Category(ctx context.Context, id int64) (CategoryRecord, error)
	Exercise(ctx context.Context, id int64) (ExerciseRecord, error)
	Template(ctx context.Context, id int64) (TemplateRecord, error)
	Program(ctx context.Context, id int64) (ProgramRecord, error)

	CreateEquipment(ctx context.Context, slug string, r EquipmentRecord, now time.Time) (int64, error)
	CreateCategory(ctx context.Context, slug string, r CategoryRecord, now time.Time) (int64, error)
	CreateExercise(ctx context.Context, slug string, r ExerciseRecord, now time.Time) (int64, error)
	CreateTemplate(ctx context.Context, slug string, r TemplateRecord, now time.Time) (int64, error)
	CreateProgram(ctx context.Context, slug string, r ProgramRecord, now time.Time) (int64, error)

	// The updates replace every synced field; UpdateExercise also replaces
	// the categories and restores a retired exercise, UpdateTemplate the
	// activities and UpdateProgram the schedule.
	UpdateEquipment(ctx context.Context, id int64, r EquipmentRecord, now time.Time) error
	UpdateCategory(ctx context.Context, id int64, r CategoryRecord, now time.Time) error
	UpdateExercise(ctx context.Context, id int64, r ExerciseRecord, now time.Time) error
	UpdateTemplate(ctx context.Context, id int64, r TemplateRecord, now time.Time) error
	UpdateProgram(ctx context.Context, id int64, r ProgramRecord, now time.Time) error

	// SetSlug makes the row managed.
	SetSlug(ctx context.Context, kind Kind, id int64, slug string) error
	// RetireExercise soft-deletes an exercise; history keeps resolving it.
	RetireExercise(ctx context.Context, id int64, now time.Time) error
	// Delete removes a row of any other kind.
	Delete(ctx context.Context, kind Kind, id int64) error

	// InUse reports what still references a row: exercises (retired ones
	// included) for equipment and categories, program assignments for a
	// template, enrollments for a program. Exercises are never blocked.
	InUse(ctx context.Context, kind Kind, id int64) (bool, error)
	// EquipmentExerciseIDs returns the exercises using the equipment,
	// retired ones included.
	EquipmentExerciseIDs(ctx context.Context, equipmentID int64) ([]int64, error)
	// HasLoggedActivities reports whether any workout logs one of the
	// exercises.
	HasLoggedActivities(ctx context.Context, exerciseIDs []int64) (bool, error)
}

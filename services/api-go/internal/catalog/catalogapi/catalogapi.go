// Package catalogapi is what other modules may know about the catalog:
// training copies a template into a workout and resolves exercises in
// history, and dashboard builds the schedule. It holds plain DTOs, already
// localized, and no catalog domain types, so the catalog can change inside
// without breaking its readers (ADR 0001). The catalog's app.Service
// implements Catalog; Fake stands in for it in other modules' tests.
package catalogapi

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

// Catalog is the catalog's read surface for other modules.
type Catalog interface {
	// ExerciseLookup returns the exercises with these ids, retired ones
	// included, keyed by id; unknown ids are left out.
	ExerciseLookup(ctx context.Context, ids []int64, locale i18n.Locale) (map[int64]ExerciseInfo, error)
	// TemplateSnapshot returns a template to copy into a workout; apperr
	// NotFound for an unknown id.
	TemplateSnapshot(ctx context.Context, id int64, locale i18n.Locale) (Snapshot, error)
	// TemplateHeaders returns the templates with these ids, keyed by id;
	// unknown (deleted) ids are left out.
	TemplateHeaders(ctx context.Context, ids []int64, locale i18n.Locale) (map[int64]TemplateHeader, error)
	// EnrolledSchedule returns every weekday assignment of the programs the
	// user is enrolled in, by program and then in assignment order.
	EnrolledSchedule(ctx context.Context, userID int64, locale i18n.Locale) ([]ScheduledTemplate, error)
	// EnrolledPrograms returns how many programs the user is enrolled in,
	// with or without scheduled templates.
	EnrolledPrograms(ctx context.Context, userID int64) (int, error)
}

// ExerciseInfo is an exercise as an activity displays it. Texts are in the
// requested locale; an empty Name, Description or EquipmentName has no text.
type ExerciseInfo struct {
	ID              int64
	Name            string
	Description     string
	EquipmentName   string
	EffortType      string
	DifficultyUnit  string
	CategoryNames   []string
	RestTimeSeconds *int
	Retired         bool
}

// TemplateHeader is a template's own fields in the requested locale; an
// empty Name or Description has no text.
type TemplateHeader struct {
	ID          int64
	Name        string
	Description string
}

// Snapshot is a template to copy: its header in the requested locale and
// its activities and sets in order, with their ids for display.
type Snapshot struct {
	TemplateHeader
	Activities []SnapshotActivity
}

// SnapshotActivity is one exercise of a Snapshot.
type SnapshotActivity struct {
	ID         int64
	ExerciseID int64
	Order      int
	Sets       []SnapshotSet
}

// SnapshotSet is one planned set of a SnapshotActivity.
type SnapshotSet struct {
	ID              int64
	Order           int
	EffortValue     int
	DifficultyValue *float64
}

// ScheduledTemplate is a template a program schedules on a weekday (the
// contract's lowercase weekday name).
type ScheduledTemplate struct {
	ProgramID    int64
	ProgramName  string
	TemplateID   int64
	TemplateName string
	Weekday      string
}

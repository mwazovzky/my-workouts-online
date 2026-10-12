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
	// EnrolledSchedule returns every weekday assignment of the programs the
	// user is enrolled in, by program and then in assignment order.
	EnrolledSchedule(ctx context.Context, userID int64, locale i18n.Locale) ([]ScheduledTemplate, error)
}

// ExerciseInfo is an exercise as an activity displays it. Names are in the
// requested locale; an empty Name or EquipmentName has no text.
type ExerciseInfo struct {
	ID              int64
	Name            string
	EquipmentName   string
	EffortType      string
	DifficultyUnit  string
	CategoryNames   []string
	RestTimeSeconds *int
	Retired         bool
}

// Snapshot is a template to copy: its name in the requested locale and its
// activities and sets in order.
type Snapshot struct {
	ID         int64
	Name       string
	Activities []SnapshotActivity
}

// SnapshotActivity is one exercise of a Snapshot.
type SnapshotActivity struct {
	ExerciseID int64
	Order      int
	Sets       []SnapshotSet
}

// SnapshotSet is one planned set of a SnapshotActivity.
type SnapshotSet struct {
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

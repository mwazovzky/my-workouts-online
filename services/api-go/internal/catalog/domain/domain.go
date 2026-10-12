// Package domain holds the catalog's types: equipment, categories,
// exercises, workout templates and programs, with their localized text.
package domain

import (
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

// LocalizedText is a translatable field: the English source text from the
// row's own column and the other locales from its translations (ADR 0004).
type LocalizedText struct {
	Source string
	Other  map[i18n.Locale]string
}

// Resolve returns the text in locale, falling back to the source text when
// that locale has no translation or a blank one, as PHP's HasTranslations
// does. An empty result means the field has no text at all.
func (t LocalizedText) Resolve(locale i18n.Locale) string {
	if text := t.Other[locale]; text != "" {
		return text
	}

	return t.Source
}

// EffortType is what a set's effort_value counts.
type EffortType string

// The effort types.
const (
	Repetitions EffortType = "repetitions"
	Duration    EffortType = "duration"
)

// DifficultyUnit is what a set's difficulty_value measures, set by the
// exercise's equipment.
type DifficultyUnit string

// The difficulty units.
const (
	Kilograms     DifficultyUnit = "kilograms"
	Pounds        DifficultyUnit = "pounds"
	Plates        DifficultyUnit = "plates"
	HeartRateZone DifficultyUnit = "heart_rate_zone"
	NoDifficulty  DifficultyUnit = "none"
)

// Weekday is a day a program schedules a template on, by its lowercase
// English name.
type Weekday string

// The weekdays, in the contract's order.
const (
	Monday    Weekday = "monday"
	Tuesday   Weekday = "tuesday"
	Wednesday Weekday = "wednesday"
	Thursday  Weekday = "thursday"
	Friday    Weekday = "friday"
	Saturday  Weekday = "saturday"
	Sunday    Weekday = "sunday"
)

var weekdays = map[Weekday]time.Weekday{
	Monday: time.Monday, Tuesday: time.Tuesday, Wednesday: time.Wednesday, Thursday: time.Thursday,
	Friday: time.Friday, Saturday: time.Saturday, Sunday: time.Sunday,
}

// Valid reports whether w is one of the seven weekdays.
func (w Weekday) Valid() bool {
	_, ok := weekdays[w]

	return ok
}

// Equipment is what an exercise is performed with; its unit decides what a
// set's difficulty_value means.
type Equipment struct {
	ID             int64
	Name           LocalizedText
	DifficultyUnit DifficultyUnit
}

// Category groups exercises (Chest, Legs, …).
type Category struct {
	ID   int64
	Name LocalizedText
}

// Exercise is a movement in the catalog. A retired exercise is hidden from
// the picker but still resolves wherever it is already used.
type Exercise struct {
	ID              int64
	Name            LocalizedText
	Description     LocalizedText
	Equipment       Equipment
	EffortType      EffortType
	RestTimeSeconds *int
	Categories      []Category
	RetiredAt       *time.Time
}

// IsRetired reports whether the exercise has been retired.
func (e Exercise) IsRetired() bool { return e.RetiredAt != nil }

// WorkoutTemplate is a planned workout: activities in order, each with its
// planned sets. Activities is nil when only the template's own fields were
// loaded (a template listed inside a program).
type WorkoutTemplate struct {
	ID          int64
	Name        LocalizedText
	Description LocalizedText
	Activities  []TemplateActivity
}

// TemplateActivity is one exercise of a template.
type TemplateActivity struct {
	ID       int64
	Order    int
	Exercise Exercise
	Sets     []TemplateSet
}

// TemplateSet is one planned set. DifficultyValue is nil for equipment whose
// unit is none.
type TemplateSet struct {
	ID              int64
	Order           int
	EffortValue     int
	DifficultyValue *float64
}

// Program schedules templates on weekdays. A template may appear on several
// weekdays, and Assignments keeps the order they were assigned in.
type Program struct {
	ID          int64
	Name        LocalizedText
	Description LocalizedText
	Assignments []Assignment
}

// Assignment schedules a template (its own fields only) on a weekday.
type Assignment struct {
	Template WorkoutTemplate
	Weekday  Weekday
}

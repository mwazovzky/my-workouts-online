// Package app holds the dashboard's one use case: the signed-in home page,
// composed from the catalog (enrolled programs and their schedule) and
// training (workouts and completion counts) through their api packages.
// The dashboard owns no tables and writes nothing (ADR 0011).
package app

import (
	"context"
	"maps"
	"slices"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/catalogapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/trainingapi"
)

// recentWorkouts is how many workouts the dashboard lists, as in PHP.
const recentWorkouts = 5

// completionWindow is the "last 7 days" of the summary: an exact span back
// from now, not calendar days.
const completionWindow = 7 * 24 * time.Hour

// The other modules' data a Dashboard shows, re-exported so the HTTP
// adapter depends on this module only.
type (
	// Workout is a workout as a list shows it.
	Workout = trainingapi.WorkoutSummary
	// TemplateHeader is a template's localized name and description.
	TemplateHeader = catalogapi.TemplateHeader
)

// Deps are the Service's collaborators.
type Deps struct {
	Catalog  catalogapi.Catalog
	Training trainingapi.Training
	Clock    clock.Clock
}

// Service builds the dashboard.
type Service struct {
	Deps
}

// New returns a Service.
func New(deps Deps) *Service {
	return &Service{Deps: deps}
}

// Dashboard is the signed-in user's home page.
type Dashboard struct {
	Upcoming []Scheduled
	// InProgress is the most recently updated unfinished workout, if any.
	InProgress *Workout
	Recent     []Workout
	// Templates are the headers of the workouts' templates, by id; a
	// deleted template is missing.
	Templates map[int64]TemplateHeader
	Summary   Summary
}

// Summary are the dashboard's counts.
type Summary struct {
	EnrolledPrograms   int
	CompletedWorkouts  int
	CompletedLast7Days int
	UpcomingWorkouts   int
}

// Get builds the user's dashboard in locale.
func (s *Service) Get(ctx context.Context, userID int64, locale i18n.Locale) (Dashboard, error) {
	now := s.Clock.Now()

	schedule, err := s.Catalog.EnrolledSchedule(ctx, userID, locale)
	if err != nil {
		return Dashboard{}, err
	}

	enrolled, err := s.Catalog.EnrolledPrograms(ctx, userID)
	if err != nil {
		return Dashboard{}, err
	}

	assignments := make([]Assignment, len(schedule))
	for i, a := range schedule {
		assignments[i] = Assignment{
			ProgramID: a.ProgramID, ProgramName: a.ProgramName, TemplateID: a.TemplateID, TemplateName: a.TemplateName, Weekday: a.Weekday,
		}
	}

	d := Dashboard{Upcoming: Upcoming(assignments, Today(now))}

	inProgress, found, err := s.Training.InProgress(ctx, userID)
	if err != nil {
		return Dashboard{}, err
	}

	if found {
		d.InProgress = &inProgress
	}

	if d.Recent, err = s.Training.RecentWorkouts(ctx, userID, recentWorkouts); err != nil {
		return Dashboard{}, err
	}

	counts, err := s.Training.Counts(ctx, userID, now.Add(-completionWindow))
	if err != nil {
		return Dashboard{}, err
	}

	if d.Templates, err = s.templates(ctx, d, locale); err != nil {
		return Dashboard{}, err
	}

	d.Summary = Summary{
		EnrolledPrograms: enrolled, CompletedWorkouts: counts.Completed, CompletedLast7Days: counts.CompletedSince,
		UpcomingWorkouts: len(d.Upcoming),
	}

	return d, nil
}

func (s *Service) templates(ctx context.Context, d Dashboard, locale i18n.Locale) (map[int64]TemplateHeader, error) {
	ids := map[int64]bool{}

	workouts := d.Recent
	if d.InProgress != nil {
		workouts = append(slices.Clone(workouts), *d.InProgress)
	}

	for _, w := range workouts {
		if w.TemplateID != nil {
			ids[*w.TemplateID] = true
		}
	}

	if len(ids) == 0 {
		return map[int64]TemplateHeader{}, nil
	}

	return s.Catalog.TemplateHeaders(ctx, slices.Sorted(maps.Keys(ids)), locale)
}

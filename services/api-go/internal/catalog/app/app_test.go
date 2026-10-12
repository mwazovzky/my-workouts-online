package app_test

import (
	"context"
	"errors"
	"log/slog"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/catalogapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

var now = time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC)

// fakePrograms is an in-memory ports.Programs.
type fakePrograms struct {
	programs map[int64]domain.Program
	enrolled map[[2]int64]time.Time
	schedule []ports.ScheduledTemplate
	err      error
}

func (f *fakePrograms) List(context.Context, int64) ([]ports.ProgramEntry, error) {
	return nil, f.err
}

func (f *fakePrograms) ByID(_ context.Context, id, userID int64) (ports.ProgramEntry, error) {
	p, ok := f.programs[id]
	if !ok {
		return ports.ProgramEntry{}, apperr.NotFound(nil)
	}

	_, enrolled := f.enrolled[[2]int64{id, userID}]

	return ports.ProgramEntry{Program: p, Enrolled: enrolled}, f.err
}

func (f *fakePrograms) Exists(_ context.Context, id int64) (bool, error) {
	_, ok := f.programs[id]

	return ok, f.err
}

func (f *fakePrograms) Enroll(_ context.Context, programID, userID int64, at time.Time) error {
	f.enrolled[[2]int64{programID, userID}] = at

	return nil
}

func (f *fakePrograms) Unenroll(_ context.Context, programID, userID int64) error {
	delete(f.enrolled, [2]int64{programID, userID})

	return nil
}

func (f *fakePrograms) EnrolledSchedule(context.Context, int64) ([]ports.ScheduledTemplate, error) {
	return f.schedule, f.err
}

// fakeTemplates is an in-memory ports.Templates.
type fakeTemplates map[int64]domain.WorkoutTemplate

func (f fakeTemplates) ByID(_ context.Context, id int64) (domain.WorkoutTemplate, error) {
	t, ok := f[id]
	if !ok {
		return domain.WorkoutTemplate{}, apperr.NotFound(nil)
	}

	return t, nil
}

func (f fakeTemplates) Headers(_ context.Context, ids []int64) ([]domain.WorkoutTemplate, error) {
	var found []domain.WorkoutTemplate

	for _, id := range ids {
		if t, ok := f[id]; ok {
			found = append(found, domain.WorkoutTemplate{ID: t.ID, Name: t.Name, Description: t.Description})
		}
	}

	return found, nil
}

// fakeExercises is an in-memory ports.Exercises, in id order.
type fakeExercises struct {
	exercises []domain.Exercise
	err       error
}

func (f *fakeExercises) ListActive(context.Context) ([]domain.Exercise, error) {
	var active []domain.Exercise

	for _, e := range f.exercises {
		if !e.IsRetired() {
			active = append(active, e)
		}
	}

	return active, f.err
}

func (f *fakeExercises) ByIDs(_ context.Context, ids []int64) ([]domain.Exercise, error) {
	var found []domain.Exercise

	for _, e := range f.exercises {
		for _, id := range ids {
			if e.ID == id {
				found = append(found, e)
			}
		}
	}

	return found, f.err
}

func text(en, ru string) domain.LocalizedText {
	t := domain.LocalizedText{Source: en}
	if ru != "" {
		t.Other = map[i18n.Locale]string{i18n.RU: ru}
	}

	return t
}

var retired = now.Add(-time.Hour)

var barbell = domain.Equipment{ID: 1, Name: text("Barbell", "Штанга"), DifficultyUnit: domain.Kilograms}

var catalog = []domain.Exercise{
	{ID: 1, Name: text("Squat", "Приседания"), Equipment: barbell, EffortType: domain.Repetitions, Categories: []domain.Category{{ID: 2, Name: text("Legs", "Ноги")}}},
	{ID: 2, Name: text("bench press", "Жим лёжа"), Equipment: barbell, EffortType: domain.Repetitions},
	{ID: 3, Name: text("Upright Row", "Тяга к подбородку"), Equipment: barbell, EffortType: domain.Repetitions, RetiredAt: &retired},
	{ID: 4, Name: text("Plank", ""), Equipment: barbell, EffortType: domain.Duration},
	{ID: 5, Name: text("Ёлочка", ""), Equipment: barbell, EffortType: domain.Repetitions},
	{ID: 6, Name: text("Burpee", "Бёрпи"), Equipment: barbell, EffortType: domain.Repetitions},
	{ID: 7, Name: text("burpee", "Бёрпи"), Equipment: barbell, EffortType: domain.Repetitions},
}

type fixture struct {
	service   *app.Service
	programs  *fakePrograms
	exercises *fakeExercises
}

func newFixture() fixture {
	programs := &fakePrograms{
		programs: map[int64]domain.Program{1: {ID: 1, Name: text("Strength", "Сила")}},
		enrolled: map[[2]int64]time.Time{},
	}
	exercises := &fakeExercises{exercises: catalog}
	templates := fakeTemplates{1: {
		ID: 1, Name: text("Full Body A", "Всё тело А"), Description: text("Squat and row.", ""),
		Activities: []domain.TemplateActivity{
			{ID: 10, Order: 1, Exercise: catalog[0], Sets: []domain.TemplateSet{{ID: 100, Order: 1, EffortValue: 5, DifficultyValue: new(100.0)}}},
			{ID: 11, Order: 2, Exercise: catalog[2], Sets: []domain.TemplateSet{{ID: 101, Order: 1, EffortValue: 10}}},
		},
	}}

	service := app.New(app.Deps{
		Programs: programs, Templates: templates, Exercises: exercises,
		Clock: clock.NewFake(now), Logger: slog.New(slog.DiscardHandler),
	})

	return fixture{service: service, programs: programs, exercises: exercises}
}

func names(exercises []domain.Exercise, locale i18n.Locale) []string {
	out := make([]string, len(exercises))
	for i, e := range exercises {
		out[i] = e.Name.Resolve(locale)
	}

	return out
}

func TestListExercisesSortsByLocalizedNameIgnoringCase(t *testing.T) {
	t.Parallel()

	f := newFixture()

	tests := map[i18n.Locale][]string{
		// "Burpee" and "burpee" tie and keep id order; Ё sorts after the
		// Latin letters.
		i18n.EN: {"bench press", "Burpee", "burpee", "Plank", "Squat", "Ёлочка"},
		// Untranslated names fall back to English, which sorts before
		// Cyrillic; Ё sorts after the rest of the alphabet, as in PHP's
		// byte order.
		i18n.RU: {"Plank", "Бёрпи", "Бёрпи", "Жим лёжа", "Приседания", "Ёлочка"},
	}

	for locale, want := range tests {
		got, err := f.service.ListExercises(t.Context(), locale)
		if err != nil {
			t.Fatal(err)
		}

		if diff := cmp.Diff(want, names(got, locale)); diff != "" {
			t.Errorf("%s (-want +got):\n%s", locale, diff)
		}
	}

	f.exercises.err = errors.New("boom")
	if _, err := f.service.ListExercises(t.Context(), i18n.EN); err == nil {
		t.Error("a repository error was swallowed")
	}
}

func TestEnrollAndUnenrollCheckTheProgramExists(t *testing.T) {
	t.Parallel()

	f := newFixture()
	ada := auth.Principal{UserID: 7}

	for _, fn := range []func(context.Context, auth.Principal, int64) error{f.service.Enroll, f.service.Unenroll} {
		if err := fn(t.Context(), ada, 99); !apperr.HasKind(err, apperr.KindNotFound) {
			t.Errorf("unknown program: err = %v, want not found", err)
		}
	}

	if err := f.service.Enroll(t.Context(), ada, 1); err != nil {
		t.Fatal(err)
	}

	if at, ok := f.programs.enrolled[[2]int64{1, 7}]; !ok || !at.Equal(now) {
		t.Errorf("enrollment = %v, %v; want one at %s", at, ok, now)
	}

	entry, err := f.service.GetProgram(t.Context(), ada, 1)
	if err != nil || !entry.Enrolled {
		t.Errorf("GetProgram = %+v, %v; want enrolled", entry, err)
	}

	if err := f.service.Unenroll(t.Context(), ada, 1); err != nil {
		t.Fatal(err)
	}

	if len(f.programs.enrolled) != 0 {
		t.Errorf("enrollments left: %v", f.programs.enrolled)
	}

	f.programs.err = errors.New("boom")
	if err := f.service.Enroll(t.Context(), ada, 1); err == nil {
		t.Error("a repository error was swallowed")
	}

	if _, err := f.service.ListPrograms(t.Context(), ada); err == nil {
		t.Error("a repository error was swallowed")
	}
}

func TestTemplateSnapshotKeepsRetiredExercises(t *testing.T) {
	t.Parallel()

	f := newFixture()

	got, err := f.service.TemplateSnapshot(t.Context(), 1, i18n.RU)
	if err != nil {
		t.Fatal(err)
	}

	want := catalogapi.Snapshot{
		TemplateHeader: catalogapi.TemplateHeader{ID: 1, Name: "Всё тело А", Description: "Squat and row."},
		Activities: []catalogapi.SnapshotActivity{
			{ID: 10, ExerciseID: 1, Order: 1, Sets: []catalogapi.SnapshotSet{{ID: 100, Order: 1, EffortValue: 5, DifficultyValue: new(100.0)}}},
			{ID: 11, ExerciseID: 3, Order: 2, Sets: []catalogapi.SnapshotSet{{ID: 101, Order: 1, EffortValue: 10}}},
		},
	}
	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("snapshot (-want +got):\n%s", diff)
	}

	if _, err := f.service.TemplateSnapshot(t.Context(), 99, i18n.RU); !apperr.HasKind(err, apperr.KindNotFound) {
		t.Errorf("unknown template: err = %v, want not found", err)
	}

	if template, err := f.service.GetWorkoutTemplate(t.Context(), 1); err != nil || len(template.Activities) != 2 {
		t.Errorf("GetWorkoutTemplate = %+v, %v", template, err)
	}
}

func TestTemplateHeadersSkipUnknownIDs(t *testing.T) {
	t.Parallel()

	got, err := newFixture().service.TemplateHeaders(t.Context(), []int64{1, 99}, i18n.RU)
	if err != nil {
		t.Fatal(err)
	}

	want := map[int64]catalogapi.TemplateHeader{1: {ID: 1, Name: "Всё тело А", Description: "Squat and row."}}
	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("headers (-want +got):\n%s", diff)
	}
}

func TestExerciseLookupResolvesRetiredExercisesInTheLocale(t *testing.T) {
	t.Parallel()

	f := newFixture()

	got, err := f.service.ExerciseLookup(t.Context(), []int64{1, 3, 99}, i18n.RU)
	if err != nil {
		t.Fatal(err)
	}

	want := map[int64]catalogapi.ExerciseInfo{
		1: {ID: 1, Name: "Приседания", EquipmentName: "Штанга", EffortType: "repetitions", DifficultyUnit: "kilograms", CategoryNames: []string{"Ноги"}},
		3: {ID: 3, Name: "Тяга к подбородку", EquipmentName: "Штанга", EffortType: "repetitions", DifficultyUnit: "kilograms", CategoryNames: []string{}, Retired: true},
	}
	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("lookup (-want +got):\n%s", diff)
	}

	f.exercises.err = errors.New("boom")
	if _, err := f.service.ExerciseLookup(t.Context(), []int64{1}, i18n.RU); err == nil {
		t.Error("a repository error was swallowed")
	}
}

func TestEnrolledScheduleIsLocalized(t *testing.T) {
	t.Parallel()

	f := newFixture()
	f.programs.schedule = []ports.ScheduledTemplate{
		{ProgramID: 1, ProgramName: text("Strength", "Сила"), TemplateID: 2, TemplateName: text("Core", ""), Weekday: domain.Monday},
	}

	got, err := f.service.EnrolledSchedule(t.Context(), 7, i18n.RU)
	if err != nil {
		t.Fatal(err)
	}

	want := []catalogapi.ScheduledTemplate{{ProgramID: 1, ProgramName: "Сила", TemplateID: 2, TemplateName: "Core", Weekday: "monday"}}
	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("schedule (-want +got):\n%s", diff)
	}

	f.programs.err = errors.New("boom")
	if _, err := f.service.EnrolledSchedule(t.Context(), 7, i18n.RU); err == nil {
		t.Error("a repository error was swallowed")
	}
}

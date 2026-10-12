package app_test

import (
	"errors"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/catalogapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/dashboard/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/trainingapi"
)

var now = time.Date(2025, 1, 6, 10, 0, 0, 0, time.UTC) // a Monday

func newService(catalog *catalogapi.Fake, training *trainingapi.Fake) *app.Service {
	return app.New(app.Deps{Catalog: catalog, Training: training, Clock: clock.NewFake(now)})
}

func TestGetComposesTheDashboard(t *testing.T) {
	t.Parallel()

	template := int64(22)
	catalog := &catalogapi.Fake{
		Schedules: map[int64][]catalogapi.ScheduledTemplate{7: {
			{ProgramID: 1, ProgramName: "Сила", TemplateID: 22, TemplateName: "Всё тело", Weekday: "tuesday"},
			{ProgramID: 1, ProgramName: "Сила", TemplateID: 22, TemplateName: "Всё тело", Weekday: "monday"},
		}},
		Enrolled:  map[int64]int{7: 2},
		Templates: map[int64]catalogapi.Snapshot{22: {TemplateHeader: catalogapi.TemplateHeader{ID: 22, Name: "Всё тело"}}},
	}
	training := &trainingapi.Fake{
		Workouts: map[int64][]trainingapi.WorkoutSummary{7: {
			{ID: 3, Status: "completed"}, {ID: 2, Status: "in_progress", TemplateID: &template}, {ID: 1, Status: "completed"},
		}},
		Counted: trainingapi.Counts{Completed: 2, CompletedSince: 1},
	}

	got, err := newService(catalog, training).Get(t.Context(), 7, i18n.RU)
	if err != nil {
		t.Fatal(err)
	}

	if got.InProgress == nil || got.InProgress.ID != 2 || len(got.Recent) != 3 {
		t.Errorf("workouts: in progress %+v, recent %d", got.InProgress, len(got.Recent))
	}

	if got.Upcoming[0].ID != "1-22-2025-01-06" || got.Upcoming[1].ID != "1-22-2025-01-07" {
		t.Errorf("upcoming = %+v", got.Upcoming)
	}

	if got.Templates[22].Name != "Всё тело" {
		t.Errorf("templates = %+v", got.Templates)
	}

	want := app.Summary{EnrolledPrograms: 2, CompletedWorkouts: 2, CompletedLast7Days: 1, UpcomingWorkouts: 2}
	if diff := cmp.Diff(want, got.Summary); diff != "" {
		t.Errorf("summary (-want +got):\n%s", diff)
	}
}

func TestGetWithNothingToShow(t *testing.T) {
	t.Parallel()

	got, err := newService(&catalogapi.Fake{}, &trainingapi.Fake{}).Get(t.Context(), 7, i18n.EN)
	if err != nil {
		t.Fatal(err)
	}

	if got.InProgress != nil || len(got.Recent) != 0 || len(got.Upcoming) != 0 || got.Summary != (app.Summary{}) {
		t.Errorf("dashboard = %+v", got)
	}
}

func TestGetPassesErrorsOn(t *testing.T) {
	t.Parallel()

	boom := errors.New("boom")

	if _, err := newService(&catalogapi.Fake{Err: boom}, &trainingapi.Fake{}).Get(t.Context(), 7, i18n.EN); !errors.Is(err, boom) {
		t.Errorf("catalog failure: err = %v", err)
	}

	if _, err := newService(&catalogapi.Fake{}, &trainingapi.Fake{Err: boom}).Get(t.Context(), 7, i18n.EN); !errors.Is(err, boom) {
		t.Errorf("training failure: err = %v", err)
	}
}

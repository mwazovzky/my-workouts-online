package catalogapi_test

import (
	"errors"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/catalogapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

func TestFake(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	f := &catalogapi.Fake{
		Exercises: map[int64]catalogapi.ExerciseInfo{1: {ID: 1, Name: "Squat"}},
		Templates: map[int64]catalogapi.Snapshot{2: {TemplateHeader: catalogapi.TemplateHeader{ID: 2, Name: "Full Body A"}}},
		Schedules: map[int64][]catalogapi.ScheduledTemplate{7: {{ProgramID: 3, Weekday: "monday"}}},
	}

	if got, err := f.ExerciseLookup(ctx, []int64{1, 99}, i18n.EN); err != nil || len(got) != 1 || got[1].Name != "Squat" {
		t.Errorf("ExerciseLookup = %v, %v", got, err)
	}

	if got, err := f.TemplateSnapshot(ctx, 2, i18n.EN); err != nil || got.Name != "Full Body A" {
		t.Errorf("TemplateSnapshot = %v, %v", got, err)
	}

	if _, err := f.TemplateSnapshot(ctx, 99, i18n.EN); !apperr.HasKind(err, apperr.KindNotFound) {
		t.Errorf("unknown template: err = %v, want not found", err)
	}

	if got, err := f.TemplateHeaders(ctx, []int64{2, 99}, i18n.EN); err != nil || len(got) != 1 || got[2].Name != "Full Body A" {
		t.Errorf("TemplateHeaders = %v, %v", got, err)
	}

	if got, err := f.EnrolledSchedule(ctx, 7, i18n.EN); err != nil || len(got) != 1 {
		t.Errorf("EnrolledSchedule = %v, %v", got, err)
	}

	f.Err = errors.New("boom")

	if _, err := f.ExerciseLookup(ctx, nil, i18n.EN); err == nil {
		t.Error("ExerciseLookup ignored Err")
	}

	if _, err := f.TemplateSnapshot(ctx, 2, i18n.EN); err == nil {
		t.Error("TemplateSnapshot ignored Err")
	}

	if _, err := f.EnrolledSchedule(ctx, 7, i18n.EN); err == nil {
		t.Error("EnrolledSchedule ignored Err")
	}

	if _, err := f.TemplateHeaders(ctx, nil, i18n.EN); err == nil {
		t.Error("TemplateHeaders ignored Err")
	}
}

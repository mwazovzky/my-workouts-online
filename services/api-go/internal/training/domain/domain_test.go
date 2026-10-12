package domain_test

import (
	"errors"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/domain"
)

var now = time.Date(2026, 10, 12, 9, 0, 0, 0, time.UTC)

func TestSetCheckCompletion(t *testing.T) {
	t.Parallel()

	tests := []struct {
		set  domain.Set
		want error
	}{
		{domain.Set{IsCompleted: true, EffortValue: 0}, domain.ErrCompletedSetRequiresEffort},
		{domain.Set{IsCompleted: true, EffortValue: 5}, nil},
		{domain.Set{IsCompleted: false, EffortValue: 0}, nil},
	}

	for _, tt := range tests {
		if err := tt.set.CheckCompletion(); !errors.Is(err, tt.want) {
			t.Errorf("%+v: err = %v, want %v", tt.set, err, tt.want)
		}
	}
}

func TestCompleteBumpsTheVersionOnce(t *testing.T) {
	t.Parallel()

	w := domain.Workout{ID: 7, Status: domain.InProgress, Version: 3}

	if err := w.Complete(now); err != nil {
		t.Fatal(err)
	}

	if w.Status != domain.Completed || w.Version != 4 || !w.UpdatedAt.Equal(now) || w.ETag() != `"7-4"` {
		t.Errorf("workout = %+v", w)
	}

	if err := w.Complete(now); !errors.Is(err, domain.ErrNotInProgress) || w.Version != 4 {
		t.Errorf("second Complete: err = %v, version %d", err, w.Version)
	}

	if err := w.CanChange(); !errors.Is(err, domain.ErrNotInProgress) {
		t.Errorf("CanChange on a completed workout = %v", err)
	}
}

func TestRepeatCopiesSetsUncompletedWithoutTheTemplate(t *testing.T) {
	t.Parallel()

	template := int64(3)
	difficulty := 60.0
	source := domain.Workout{
		ID: 7, UserID: 2, TemplateID: &template, Name: "Push A", Status: domain.Completed, Version: 5,
		Activities: []domain.Activity{{ID: 70, ExerciseID: 15, Order: 1, Sets: []domain.Set{
			{ID: 700, Order: 1, EffortValue: 5, DifficultyValue: &difficulty, IsCompleted: true},
		}}},
	}

	got, err := source.Repeat(now)
	if err != nil {
		t.Fatal(err)
	}

	want := domain.Workout{
		UserID: 2, Name: "Push A", Status: domain.InProgress, Version: 1, CreatedAt: now, UpdatedAt: now,
		Activities: []domain.Activity{{ExerciseID: 15, Order: 1, Sets: []domain.Set{{Order: 1, EffortValue: 5, DifficultyValue: &difficulty}}}},
	}
	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("copy (-want +got):\n%s", diff)
	}

	source.Status = domain.InProgress
	if _, err := source.Repeat(now); !errors.Is(err, domain.ErrNotCompleted) {
		t.Errorf("repeating an unfinished workout: err = %v", err)
	}
}

func TestMatchesIfMatch(t *testing.T) {
	t.Parallel()

	w := domain.Workout{ID: 42, Version: 7}

	tests := map[string]bool{
		`"42-7"`:          true,
		` "42-7" `:        true,
		`*`:               true,
		`"42-6", "42-7"`:  true,
		`"42-6"`:          false,
		`W/"42-7"`:        false,
		`42-7`:            false,
		``:                false,
		`"42-6",W/"42-7"`: false,
		`"41-7", "42-7" `: true,
		`"420-7"`:         false,
	}

	for ifMatch, want := range tests {
		if got := w.Matches(ifMatch); got != want {
			t.Errorf("Matches(%q) = %v, want %v", ifMatch, got, want)
		}
	}
}

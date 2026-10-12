package catalogapi

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

// Fake is an in-memory Catalog for other modules' tests. It ignores the
// locale: the values are returned as set.
type Fake struct {
	Exercises map[int64]ExerciseInfo
	Templates map[int64]Snapshot
	// Schedules is keyed by user id.
	Schedules map[int64][]ScheduledTemplate
	// Err, when set, is returned by every method.
	Err error
}

var _ Catalog = (*Fake)(nil)

// ExerciseLookup implements Catalog.
func (f *Fake) ExerciseLookup(_ context.Context, ids []int64, _ i18n.Locale) (map[int64]ExerciseInfo, error) {
	if f.Err != nil {
		return nil, f.Err
	}

	found := map[int64]ExerciseInfo{}
	for _, id := range ids {
		if e, ok := f.Exercises[id]; ok {
			found[id] = e
		}
	}

	return found, nil
}

// TemplateSnapshot implements Catalog.
func (f *Fake) TemplateSnapshot(_ context.Context, id int64, _ i18n.Locale) (Snapshot, error) {
	if f.Err != nil {
		return Snapshot{}, f.Err
	}

	s, ok := f.Templates[id]
	if !ok {
		return Snapshot{}, apperr.NotFound(nil)
	}

	return s, nil
}

// EnrolledSchedule implements Catalog.
func (f *Fake) EnrolledSchedule(_ context.Context, userID int64, _ i18n.Locale) ([]ScheduledTemplate, error) {
	if f.Err != nil {
		return nil, f.Err
	}

	return f.Schedules[userID], nil
}

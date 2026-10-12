package app

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/catalogapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

var _ catalogapi.Catalog = (*Service)(nil)

// ExerciseLookup implements catalogapi.Catalog.
func (s *Service) ExerciseLookup(ctx context.Context, ids []int64, locale i18n.Locale) (map[int64]catalogapi.ExerciseInfo, error) {
	exercises, err := s.Exercises.ByIDs(ctx, ids)
	if err != nil {
		return nil, err
	}

	found := make(map[int64]catalogapi.ExerciseInfo, len(exercises))
	for _, e := range exercises {
		found[e.ID] = exerciseInfo(e, locale)
	}

	return found, nil
}

// exerciseInfo is e as an activity displays it in locale.
func exerciseInfo(e domain.Exercise, locale i18n.Locale) catalogapi.ExerciseInfo {
	categories := make([]string, len(e.Categories))
	for i, c := range e.Categories {
		categories[i] = c.Name.Resolve(locale)
	}

	return catalogapi.ExerciseInfo{
		ID:              e.ID,
		Name:            e.Name.Resolve(locale),
		EquipmentName:   e.Equipment.Name.Resolve(locale),
		EffortType:      string(e.EffortType),
		DifficultyUnit:  string(e.Equipment.DifficultyUnit),
		CategoryNames:   categories,
		RestTimeSeconds: e.RestTimeSeconds,
		Retired:         e.IsRetired(),
	}
}

// TemplateSnapshot implements catalogapi.Catalog.
func (s *Service) TemplateSnapshot(ctx context.Context, id int64, locale i18n.Locale) (catalogapi.Snapshot, error) {
	template, err := s.Templates.ByID(ctx, id)
	if err != nil {
		return catalogapi.Snapshot{}, err
	}

	snapshot := catalogapi.Snapshot{
		ID:         template.ID,
		Name:       template.Name.Resolve(locale),
		Activities: make([]catalogapi.SnapshotActivity, len(template.Activities)),
	}

	for i, a := range template.Activities {
		sets := make([]catalogapi.SnapshotSet, len(a.Sets))
		for j, set := range a.Sets {
			sets[j] = catalogapi.SnapshotSet{Order: set.Order, EffortValue: set.EffortValue, DifficultyValue: set.DifficultyValue}
		}

		snapshot.Activities[i] = catalogapi.SnapshotActivity{ExerciseID: a.Exercise.ID, Order: a.Order, Sets: sets}
	}

	return snapshot, nil
}

// EnrolledSchedule implements catalogapi.Catalog.
func (s *Service) EnrolledSchedule(ctx context.Context, userID int64, locale i18n.Locale) ([]catalogapi.ScheduledTemplate, error) {
	assignments, err := s.Programs.EnrolledSchedule(ctx, userID)
	if err != nil {
		return nil, err
	}

	schedule := make([]catalogapi.ScheduledTemplate, len(assignments))
	for i, a := range assignments {
		schedule[i] = catalogapi.ScheduledTemplate{
			ProgramID: a.ProgramID, ProgramName: a.ProgramName.Resolve(locale),
			TemplateID: a.TemplateID, TemplateName: a.TemplateName.Resolve(locale),
			Weekday: string(a.Weekday),
		}
	}

	return schedule, nil
}

package postgres

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// Templates implements ports.Templates.
type Templates struct {
	tx        *postgres.Transactor
	exercises *Exercises
}

var _ ports.Templates = (*Templates)(nil)

// NewTemplates returns the Templates repository.
func NewTemplates(tx *postgres.Transactor) *Templates {
	return &Templates{tx: tx, exercises: NewExercises(tx)}
}

// Headers implements ports.Templates.
func (r *Templates) Headers(ctx context.Context, ids []int64) ([]domain.WorkoutTemplate, error) {
	if len(ids) == 0 {
		return []domain.WorkoutTemplate{}, nil
	}

	rows, err := sqlcgen.New(r.tx.Querier(ctx)).ListWorkoutTemplatesByID(ctx, ids)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	templates := make([]domain.WorkoutTemplate, 0, len(rows))
	for _, row := range rows {
		templateName, description, err := named(row.Name, row.Description, row.Translations)
		if err != nil {
			return nil, err
		}

		templates = append(templates, domain.WorkoutTemplate{ID: row.ID, Name: templateName, Description: description})
	}

	return templates, nil
}

// ByID implements ports.Templates: five queries (template, activities, sets,
// exercises, categories) whatever the template's size.
func (r *Templates) ByID(ctx context.Context, id int64) (domain.WorkoutTemplate, error) {
	q := sqlcgen.New(r.tx.Querier(ctx))

	row, err := q.GetWorkoutTemplate(ctx, id)
	if err != nil {
		return domain.WorkoutTemplate{}, postgres.MapError(err)
	}

	templateName, description, err := named(row.Name, row.Description, row.Translations)
	if err != nil {
		return domain.WorkoutTemplate{}, err
	}

	activities, err := q.ListTemplateActivities(ctx, id)
	if err != nil {
		return domain.WorkoutTemplate{}, postgres.MapError(err)
	}

	sets, err := q.ListTemplateSets(ctx, id)
	if err != nil {
		return domain.WorkoutTemplate{}, postgres.MapError(err)
	}

	exerciseIDs := make([]int64, len(activities))
	for i, a := range activities {
		exerciseIDs[i] = a.ExerciseID
	}

	exercises, err := r.exercises.ByIDs(ctx, exerciseIDs)
	if err != nil {
		return domain.WorkoutTemplate{}, err
	}

	byID := make(map[int64]domain.Exercise, len(exercises))
	for _, e := range exercises {
		byID[e.ID] = e
	}

	setsByActivity := map[int64][]domain.TemplateSet{}
	for _, s := range sets {
		setsByActivity[s.ActivityID] = append(setsByActivity[s.ActivityID], domain.TemplateSet{
			ID: s.ID, Order: int(s.Order), EffortValue: int(s.EffortValue), DifficultyValue: s.DifficultyValue,
		})
	}

	template := domain.WorkoutTemplate{
		ID: row.ID, Name: templateName, Description: description,
		Activities: make([]domain.TemplateActivity, 0, len(activities)),
	}

	for _, a := range activities {
		activitySets := setsByActivity[a.ID]
		if activitySets == nil {
			activitySets = []domain.TemplateSet{}
		}

		template.Activities = append(template.Activities, domain.TemplateActivity{
			ID: a.ID, Order: int(a.Order), Exercise: byID[a.ExerciseID], Sets: activitySets,
		})
	}

	return template, nil
}

package postgres

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// Exercises implements ports.Exercises.
type Exercises struct {
	tx *postgres.Transactor
}

var _ ports.Exercises = (*Exercises)(nil)

// NewExercises returns the Exercises repository.
func NewExercises(tx *postgres.Transactor) *Exercises {
	return &Exercises{tx: tx}
}

func (r *Exercises) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(r.tx.Querier(ctx))
}

// ListActive implements ports.Exercises.
func (r *Exercises) ListActive(ctx context.Context) ([]domain.Exercise, error) {
	rows, err := r.q(ctx).ListActiveExercises(ctx)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	exercises := make([]exerciseRow, len(rows))
	for i, row := range rows {
		exercises[i] = exerciseRow(row)
	}

	return r.withCategories(ctx, exercises)
}

// ByIDs implements ports.Exercises.
func (r *Exercises) ByIDs(ctx context.Context, ids []int64) ([]domain.Exercise, error) {
	if len(ids) == 0 {
		return []domain.Exercise{}, nil
	}

	rows, err := r.q(ctx).ListExercisesByID(ctx, ids)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	exercises := make([]exerciseRow, len(rows))
	for i, row := range rows {
		exercises[i] = exerciseRow(row)
	}

	return r.withCategories(ctx, exercises)
}

// exerciseRow is the shape both exercise queries return.
type exerciseRow struct {
	ID                    int64
	Name                  string
	Description           *string
	Translations          []byte
	EffortType            string
	RestTimeSeconds       *int32
	DeletedAt             *time.Time
	EquipmentID           int64
	EquipmentName         string
	EquipmentTranslations []byte
	DifficultyUnit        string
}

// withCategories maps rows to exercises and loads every exercise's
// categories with one query.
func (r *Exercises) withCategories(ctx context.Context, rows []exerciseRow) ([]domain.Exercise, error) {
	exercises := make([]domain.Exercise, 0, len(rows))
	if len(rows) == 0 {
		return exercises, nil
	}

	ids := make([]int64, len(rows))
	index := make(map[int64]int, len(rows))

	for i, row := range rows {
		exercise, err := toExercise(row)
		if err != nil {
			return nil, err
		}

		ids[i] = row.ID
		index[row.ID] = i
		exercises = append(exercises, exercise)
	}

	categories, err := r.q(ctx).ListExerciseCategories(ctx, ids)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	for _, c := range categories {
		categoryName, err := name(c.Name, c.Translations)
		if err != nil {
			return nil, err
		}

		e := &exercises[index[c.ExerciseID]]
		e.Categories = append(e.Categories, domain.Category{ID: c.ID, Name: categoryName})
	}

	return exercises, nil
}

func toExercise(row exerciseRow) (domain.Exercise, error) {
	exerciseName, description, err := named(row.Name, row.Description, row.Translations)
	if err != nil {
		return domain.Exercise{}, err
	}

	equipmentName, err := name(row.EquipmentName, row.EquipmentTranslations)
	if err != nil {
		return domain.Exercise{}, err
	}

	var rest *int
	if row.RestTimeSeconds != nil {
		seconds := int(*row.RestTimeSeconds)
		rest = &seconds
	}

	return domain.Exercise{
		ID:          row.ID,
		Name:        exerciseName,
		Description: description,
		Equipment: domain.Equipment{
			ID: row.EquipmentID, Name: equipmentName, DifficultyUnit: domain.DifficultyUnit(row.DifficultyUnit),
		},
		EffortType:      domain.EffortType(row.EffortType),
		RestTimeSeconds: rest,
		Categories:      []domain.Category{},
		RetiredAt:       row.DeletedAt,
	}, nil
}

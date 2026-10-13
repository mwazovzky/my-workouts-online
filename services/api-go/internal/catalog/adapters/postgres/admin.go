package postgres

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// AdminLists implements ports.AdminLists.
type AdminLists struct {
	tx *postgres.Transactor
}

var _ ports.AdminLists = (*AdminLists)(nil)

// NewAdminLists returns the admin lists.
func NewAdminLists(tx *postgres.Transactor) *AdminLists {
	return &AdminLists{tx: tx}
}

func (r *AdminLists) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(r.tx.Querier(ctx))
}

// Equipment implements ports.AdminLists.
func (r *AdminLists) Equipment(ctx context.Context) ([]ports.AdminEquipment, error) {
	rows, err := r.q(ctx).ListAdminEquipment(ctx)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	out := make([]ports.AdminEquipment, len(rows))
	for i, row := range rows {
		text, err := name(row.Name, row.Translations)
		if err != nil {
			return nil, err
		}

		out[i] = ports.AdminEquipment{
			ID: row.ID, Name: text, DifficultyUnit: domain.DifficultyUnit(row.DifficultyUnit), ExercisesCount: int(row.ExercisesCount),
		}
	}

	return out, nil
}

// Categories implements ports.AdminLists.
func (r *AdminLists) Categories(ctx context.Context) ([]ports.AdminCategory, error) {
	rows, err := r.q(ctx).ListAdminCategories(ctx)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	out := make([]ports.AdminCategory, len(rows))
	for i, row := range rows {
		text, err := name(row.Name, row.Translations)
		if err != nil {
			return nil, err
		}

		out[i] = ports.AdminCategory{ID: row.ID, Name: text, ExercisesCount: int(row.ExercisesCount)}
	}

	return out, nil
}

// Templates implements ports.AdminLists.
func (r *AdminLists) Templates(ctx context.Context) ([]ports.AdminTemplate, error) {
	rows, err := r.q(ctx).ListAdminWorkoutTemplates(ctx)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	out := make([]ports.AdminTemplate, len(rows))
	for i, row := range rows {
		templateName, description, err := named(row.Name, row.Description, row.Translations)
		if err != nil {
			return nil, err
		}

		out[i] = ports.AdminTemplate{ID: row.ID, Name: templateName, Description: description, ActivitiesCount: int(row.ActivitiesCount)}
	}

	return out, nil
}

// Programs implements ports.AdminLists.
func (r *AdminLists) Programs(ctx context.Context) ([]ports.AdminProgram, error) {
	rows, err := r.q(ctx).ListAdminPrograms(ctx)
	if err != nil {
		return nil, postgres.MapError(err)
	}

	out := make([]ports.AdminProgram, len(rows))
	for i, row := range rows {
		programName, description, err := named(row.Name, row.Description, row.Translations)
		if err != nil {
			return nil, err
		}

		out[i] = ports.AdminProgram{ID: row.ID, Name: programName, Description: description, TemplatesCount: int(row.TemplatesCount)}
	}

	return out, nil
}

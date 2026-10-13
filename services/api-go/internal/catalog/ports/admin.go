package ports

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
)

// AdminLists reads the admin lists, in id order, with the counts each shows.
type AdminLists interface {
	// Equipment counts each item's active exercises.
	Equipment(ctx context.Context) ([]AdminEquipment, error)
	// Categories counts each category's active exercises.
	Categories(ctx context.Context) ([]AdminCategory, error)
	// Templates counts each template's activities.
	Templates(ctx context.Context) ([]AdminTemplate, error)
	// Programs counts each program's assignments.
	Programs(ctx context.Context) ([]AdminProgram, error)
}

// AdminEquipment is an equipment row of the admin list.
type AdminEquipment struct {
	ID             int64
	Name           domain.LocalizedText
	DifficultyUnit domain.DifficultyUnit
	ExercisesCount int
}

// AdminCategory is a category row of the admin list.
type AdminCategory struct {
	ID             int64
	Name           domain.LocalizedText
	ExercisesCount int
}

// AdminTemplate is a workout template row of the admin list.
type AdminTemplate struct {
	ID              int64
	Name            domain.LocalizedText
	Description     domain.LocalizedText
	ActivitiesCount int
}

// AdminProgram is a program row of the admin list.
type AdminProgram struct {
	ID             int64
	Name           domain.LocalizedText
	Description    domain.LocalizedText
	TemplatesCount int
}

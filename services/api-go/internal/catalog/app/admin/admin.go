// Package admin holds the catalog's admin use cases: list, show, create,
// update and delete equipment, categories, exercises, workout templates and
// programs, as PHP's Admin controllers and services do. Records created
// here have no slug, so catalog sync leaves them alone. Deletes are guarded
// where a reference would break (ADR 0012).
package admin

import (
	"context"
	"math"
	"slices"
	"strconv"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/txn"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/validate"
)

// Deps are the Service's collaborators.
type Deps struct {
	Store     ports.CatalogStore
	Lists     ports.AdminLists
	Exercises ports.Exercises
	Templates ports.Templates
	Programs  ports.Programs
	Tx        txn.Transactor
	Clock     clock.Clock
}

// Service runs the admin use cases.
type Service struct {
	Deps
}

// New returns a Service.
func New(deps Deps) *Service {
	return &Service{Deps: deps}
}

func (s *Service) now() time.Time { return s.Clock.Now().UTC().Truncate(time.Second) }

// --- Equipment ---------------------------------------------------------

// Equipment is an equipment item as the admin shows it; ExercisesCount is
// set in the list only.
type Equipment struct {
	ID             int64
	Name           domain.LocalizedText
	DifficultyUnit domain.DifficultyUnit
	ExercisesCount *int
}

// EquipmentInput is a submitted equipment item.
type EquipmentInput struct {
	DifficultyUnit domain.DifficultyUnit
	Translations   Translations
}

// ListEquipment returns every item in id order, with its active exercises
// counted.
func (s *Service) ListEquipment(ctx context.Context) ([]Equipment, error) {
	rows, err := s.Lists.Equipment(ctx)
	if err != nil {
		return nil, err
	}

	out := make([]Equipment, len(rows))
	for i, r := range rows {
		out[i] = Equipment{ID: r.ID, Name: r.Name, DifficultyUnit: r.DifficultyUnit, ExercisesCount: &r.ExercisesCount}
	}

	return out, nil
}

// GetEquipment returns an item; apperr NotFound otherwise.
func (s *Service) GetEquipment(ctx context.Context, id int64) (Equipment, error) {
	r, err := s.Store.Equipment(ctx, id)
	if err != nil {
		return Equipment{}, err
	}

	return Equipment{ID: id, Name: r.Name, DifficultyUnit: r.DifficultyUnit}, nil
}

// CreateEquipment adds an item.
func (s *Service) CreateEquipment(ctx context.Context, in EquipmentInput) (Equipment, error) {
	v := validate.New()
	texts := in.Translations.apply(v, Texts{}, false)

	if err := v.Err(); err != nil {
		return Equipment{}, err
	}

	record := ports.EquipmentRecord{Name: texts.Name, DifficultyUnit: in.DifficultyUnit}

	id, err := s.Store.CreateEquipment(ctx, nil, record, s.now())
	if err != nil {
		return Equipment{}, err
	}

	return Equipment{ID: id, Name: record.Name, DifficultyUnit: record.DifficultyUnit}, nil
}

// UpdateEquipment changes an item.
func (s *Service) UpdateEquipment(ctx context.Context, id int64, in EquipmentInput) (Equipment, error) {
	current, err := s.Store.Equipment(ctx, id)
	if err != nil {
		return Equipment{}, err
	}

	v := validate.New()
	texts := in.Translations.apply(v, Texts{Name: current.Name}, false)

	if err := v.Err(); err != nil {
		return Equipment{}, err
	}

	record := ports.EquipmentRecord{Name: texts.Name, DifficultyUnit: in.DifficultyUnit}
	if err := s.Store.UpdateEquipment(ctx, id, record, s.now()); err != nil {
		return Equipment{}, err
	}

	return Equipment{ID: id, Name: record.Name, DifficultyUnit: record.DifficultyUnit}, nil
}

// DeleteEquipment removes an item no exercise uses, retired exercises
// included: their history still names it (409 in_use otherwise).
func (s *Service) DeleteEquipment(ctx context.Context, id int64) error {
	return s.deleteGuarded(ctx, ports.KindEquipment, id, "Cannot delete equipment that is used by exercises.", func(ctx context.Context) error {
		_, err := s.Store.Equipment(ctx, id)

		return err
	})
}

func (s *Service) deleteGuarded(ctx context.Context, kind ports.Kind, id int64, inUse string, exists func(context.Context) error) error {
	return s.Tx.InTx(ctx, func(ctx context.Context) error {
		if err := exists(ctx); err != nil {
			return err
		}

		used, err := s.Store.InUse(ctx, kind, id)
		if err != nil {
			return err
		}

		if used {
			return apperr.Conflict(apperr.CodeInUse, inUse, nil)
		}

		return s.Store.Delete(ctx, kind, id)
	})
}

// --- Categories --------------------------------------------------------

// Category is a category as the admin shows it; ExercisesCount is set in
// the list only.
type Category struct {
	ID             int64
	Name           domain.LocalizedText
	ExercisesCount *int
}

// ListCategories returns every category in id order, with its active
// exercises counted.
func (s *Service) ListCategories(ctx context.Context) ([]Category, error) {
	rows, err := s.Lists.Categories(ctx)
	if err != nil {
		return nil, err
	}

	out := make([]Category, len(rows))
	for i, r := range rows {
		out[i] = Category{ID: r.ID, Name: r.Name, ExercisesCount: &r.ExercisesCount}
	}

	return out, nil
}

// GetCategory returns a category; apperr NotFound otherwise.
func (s *Service) GetCategory(ctx context.Context, id int64) (Category, error) {
	r, err := s.Store.Category(ctx, id)
	if err != nil {
		return Category{}, err
	}

	return Category{ID: id, Name: r.Name}, nil
}

// CreateCategory adds a category.
func (s *Service) CreateCategory(ctx context.Context, in Translations) (Category, error) {
	v := validate.New()
	texts := in.apply(v, Texts{}, false)

	if err := v.Err(); err != nil {
		return Category{}, err
	}

	id, err := s.Store.CreateCategory(ctx, nil, ports.CategoryRecord{Name: texts.Name}, s.now())
	if err != nil {
		return Category{}, err
	}

	return Category{ID: id, Name: texts.Name}, nil
}

// UpdateCategory renames a category.
func (s *Service) UpdateCategory(ctx context.Context, id int64, in Translations) (Category, error) {
	current, err := s.Store.Category(ctx, id)
	if err != nil {
		return Category{}, err
	}

	v := validate.New()
	texts := in.apply(v, Texts{Name: current.Name}, false)

	if err := v.Err(); err != nil {
		return Category{}, err
	}

	if err := s.Store.UpdateCategory(ctx, id, ports.CategoryRecord{Name: texts.Name}, s.now()); err != nil {
		return Category{}, err
	}

	return Category{ID: id, Name: texts.Name}, nil
}

// DeleteCategory removes a category; its exercises leave it (PHP's
// behaviour, ADR 0012).
func (s *Service) DeleteCategory(ctx context.Context, id int64) error {
	if _, err := s.Store.Category(ctx, id); err != nil {
		return err
	}

	return s.Store.Delete(ctx, ports.KindCategories, id)
}

// --- Exercises ---------------------------------------------------------

// ExerciseInput is a submitted exercise.
type ExerciseInput struct {
	EquipmentID     int64
	EffortType      domain.EffortType
	RestTimeSeconds *int
	CategoryIDs     []int64
	Translations    Translations
}

// ListExercises returns the active exercises in id order.
func (s *Service) ListExercises(ctx context.Context) ([]domain.Exercise, error) {
	return s.Exercises.ListActive(ctx)
}

// GetExercise returns an active exercise; a retired one is not found, as
// PHP's route binding hides it.
func (s *Service) GetExercise(ctx context.Context, id int64) (domain.Exercise, error) {
	found, err := s.Exercises.ByIDs(ctx, []int64{id})
	if err != nil {
		return domain.Exercise{}, err
	}

	if len(found) == 0 || found[0].IsRetired() {
		return domain.Exercise{}, apperr.NotFound(nil)
	}

	return found[0], nil
}

// CreateExercise adds an exercise with its categories.
func (s *Service) CreateExercise(ctx context.Context, in ExerciseInput) (domain.Exercise, error) {
	var id int64

	err := s.Tx.InTx(ctx, func(ctx context.Context) error {
		record, err := s.exerciseRecord(ctx, in, Texts{})
		if err != nil {
			return err
		}

		id, err = s.Store.CreateExercise(ctx, nil, record, s.now())

		return err
	})
	if err != nil {
		return domain.Exercise{}, err
	}

	return s.GetExercise(ctx, id)
}

// UpdateExercise changes an active exercise and replaces its categories.
func (s *Service) UpdateExercise(ctx context.Context, id int64, in ExerciseInput) (domain.Exercise, error) {
	err := s.Tx.InTx(ctx, func(ctx context.Context) error {
		current, err := s.GetExercise(ctx, id)
		if err != nil {
			return err
		}

		record, err := s.exerciseRecord(ctx, in, Texts{Name: current.Name, Description: current.Description})
		if err != nil {
			return err
		}

		return s.Store.UpdateExercise(ctx, id, record, s.now())
	})
	if err != nil {
		return domain.Exercise{}, err
	}

	return s.GetExercise(ctx, id)
}

// exerciseRecord validates an exercise: its equipment and categories must
// exist, each reported at its pointer.
func (s *Service) exerciseRecord(ctx context.Context, in ExerciseInput, current Texts) (ports.ExerciseRecord, error) {
	v := validate.New()
	texts := in.Translations.apply(v, current, true)

	equipment, err := s.Store.Existing(ctx, ports.KindEquipment, []int64{in.EquipmentID})
	if err != nil {
		return ports.ExerciseRecord{}, err
	}

	v.Check(equipment[in.EquipmentID], "equipment_id", apperr.FieldNotFound, "validation.exists", nil)

	categories, err := s.Store.Existing(ctx, ports.KindCategories, in.CategoryIDs)
	if err != nil {
		return ports.ExerciseRecord{}, err
	}

	for i, cid := range in.CategoryIDs {
		v.CheckItem(categories[cid], "category_ids", i, apperr.FieldNotFound, "validation.exists")
	}

	if err := v.Err(); err != nil {
		return ports.ExerciseRecord{}, err
	}

	ids := slices.Clone(in.CategoryIDs)
	slices.Sort(ids)

	return ports.ExerciseRecord{
		Name: texts.Name, Description: texts.Description, EquipmentID: in.EquipmentID, EffortType: in.EffortType,
		RestTimeSeconds: in.RestTimeSeconds, CategoryIDs: slices.Compact(ids),
	}, nil
}

// DeleteExercise retires an active exercise: it leaves the pickers, and
// templates and logged workouts keep resolving it.
func (s *Service) DeleteExercise(ctx context.Context, id int64) error {
	if _, err := s.GetExercise(ctx, id); err != nil {
		return err
	}

	return s.Store.RetireExercise(ctx, id, s.now())
}

// --- Workout templates -------------------------------------------------

// TemplateSummary is a template of the admin list.
type TemplateSummary = ports.AdminTemplate

// TemplateInput is a submitted template.
type TemplateInput struct {
	Translations Translations
	Activities   []ActivityInput
}

// ActivityInput is one activity of a submitted template.
type ActivityInput struct {
	ExerciseID int64
	Order      int
	Sets       []SetInput
}

// SetInput is one planned set.
type SetInput struct {
	Order           int
	EffortValue     int
	DifficultyValue *float64
}

// ListTemplates returns every template in id order, with its activities
// counted.
func (s *Service) ListTemplates(ctx context.Context) ([]TemplateSummary, error) {
	return s.Lists.Templates(ctx)
}

// GetTemplate returns a template with its activities, sets and exercises.
func (s *Service) GetTemplate(ctx context.Context, id int64) (domain.WorkoutTemplate, error) {
	return s.Templates.ByID(ctx, id)
}

// CreateTemplate adds a template with its activities and sets.
func (s *Service) CreateTemplate(ctx context.Context, in TemplateInput) (domain.WorkoutTemplate, error) {
	var id int64

	err := s.Tx.InTx(ctx, func(ctx context.Context) error {
		record, err := s.templateRecord(ctx, in, domain.WorkoutTemplate{})
		if err != nil {
			return err
		}

		id, err = s.Store.CreateTemplate(ctx, nil, record, s.now())

		return err
	})
	if err != nil {
		return domain.WorkoutTemplate{}, err
	}

	return s.Templates.ByID(ctx, id)
}

// UpdateTemplate replaces a template's texts, activities and sets, all or
// nothing.
func (s *Service) UpdateTemplate(ctx context.Context, id int64, in TemplateInput) (domain.WorkoutTemplate, error) {
	err := s.Tx.InTx(ctx, func(ctx context.Context) error {
		current, err := s.Templates.ByID(ctx, id)
		if err != nil {
			return err
		}

		record, err := s.templateRecord(ctx, in, current)
		if err != nil {
			return err
		}

		return s.Store.UpdateTemplate(ctx, id, record, s.now())
	})
	if err != nil {
		return domain.WorkoutTemplate{}, err
	}

	return s.Templates.ByID(ctx, id)
}

// templateRecord validates a template: exercises must exist, and a retired
// one may only stay where the template already has it (ADR 0012, as ADR
// 0010 for workouts); activity orders and set orders within an activity
// must not repeat; a heart-rate zone is a whole number from 1 to 5.
func (s *Service) templateRecord(ctx context.Context, in TemplateInput, current domain.WorkoutTemplate) (ports.TemplateRecord, error) {
	v := validate.New()
	texts := in.Translations.apply(v, Texts{Name: current.Name, Description: current.Description}, true)

	var ids []int64
	for _, a := range in.Activities {
		ids = append(ids, a.ExerciseID)
	}

	exercises, err := s.Exercises.ByIDs(ctx, ids)
	if err != nil {
		return ports.TemplateRecord{}, err
	}

	byID := map[int64]domain.Exercise{}
	for _, e := range exercises {
		byID[e.ID] = e
	}

	kept := map[int64]bool{}
	for _, a := range current.Activities {
		kept[a.Exercise.ID] = true
	}

	orders := map[int]int{}
	for _, a := range in.Activities {
		orders[a.Order]++
	}

	record := ports.TemplateRecord{Name: texts.Name, Description: texts.Description}

	for i, a := range in.Activities {
		v.Nested("activities", i, func(v *validate.Validator) {
			exercise, known := byID[a.ExerciseID]
			v.Check(known && (!exercise.IsRetired() || kept[a.ExerciseID]), "exercise_id", apperr.FieldNotFound, "validation.exists",
				map[string]string{"attribute": validate.Attribute("activities." + strconv.Itoa(i) + ".exercise_id")})
			v.Check(orders[a.Order] == 1, "order", apperr.FieldNotUnique, "validation.distinct", map[string]string{"attribute": "activities." + strconv.Itoa(i) + ".order"})

			activity := ports.ActivityRecord{ExerciseID: a.ExerciseID, Order: a.Order}
			setOrders := map[int]int{}

			for _, set := range a.Sets {
				setOrders[set.Order]++
			}

			for j, set := range a.Sets {
				v.Nested("sets", j, func(v *validate.Validator) {
					v.Check(setOrders[set.Order] == 1, "order", apperr.FieldNotUnique, "validation.distinct", nil)

					if exercise.Equipment.DifficultyUnit == domain.HeartRateZone && set.DifficultyValue != nil {
						zone := *set.DifficultyValue
						v.Check(zone == math.Trunc(zone) && zone >= 1 && zone <= 5, "difficulty_value", apperr.FieldOutOfRange,
							"Heart-rate zone must be between 1 and 5.", nil)
					}
				})

				activity.Sets = append(activity.Sets, ports.SetRecord{Order: set.Order, EffortValue: set.EffortValue, DifficultyValue: set.DifficultyValue})
			}

			record.Activities = append(record.Activities, activity)
		})
	}

	return record, v.Err()
}

// DeleteTemplate removes a template no program schedules (409 in_use
// otherwise); workouts started from it keep their copy.
func (s *Service) DeleteTemplate(ctx context.Context, id int64) error {
	return s.deleteGuarded(ctx, ports.KindWorkoutTemplates, id, "Cannot delete a workout template that is used by a program.", func(ctx context.Context) error {
		_, err := s.Store.Template(ctx, id)

		return err
	})
}

// --- Programs ----------------------------------------------------------

// ProgramSummary is a program of the admin list.
type ProgramSummary = ports.AdminProgram

// ProgramInput is a submitted program.
type ProgramInput struct {
	Translations Translations
	Assignments  []AssignmentInput
}

// AssignmentInput schedules a template on a weekday.
type AssignmentInput struct {
	TemplateID int64
	Weekday    domain.Weekday
}

// ListPrograms returns every program in id order, with its assignments
// counted.
func (s *Service) ListPrograms(ctx context.Context) ([]ProgramSummary, error) {
	return s.Lists.Programs(ctx)
}

// GetProgram returns a program with its assignments in the order they were
// made.
func (s *Service) GetProgram(ctx context.Context, id int64) (domain.Program, error) {
	entry, err := s.Programs.ByID(ctx, id, 0)

	return entry.Program, err
}

// CreateProgram adds a program with its assignments; a template may be
// scheduled on several weekdays.
func (s *Service) CreateProgram(ctx context.Context, in ProgramInput) (domain.Program, error) {
	var id int64

	err := s.Tx.InTx(ctx, func(ctx context.Context) error {
		record, err := s.programRecord(ctx, in, Texts{})
		if err != nil {
			return err
		}

		id, err = s.Store.CreateProgram(ctx, nil, record, s.now())

		return err
	})
	if err != nil {
		return domain.Program{}, err
	}

	return s.GetProgram(ctx, id)
}

// UpdateProgram replaces a program's texts and assignments.
func (s *Service) UpdateProgram(ctx context.Context, id int64, in ProgramInput) (domain.Program, error) {
	err := s.Tx.InTx(ctx, func(ctx context.Context) error {
		current, err := s.Store.Program(ctx, id)
		if err != nil {
			return err
		}

		record, err := s.programRecord(ctx, in, Texts{Name: current.Name, Description: current.Description})
		if err != nil {
			return err
		}

		return s.Store.UpdateProgram(ctx, id, record, s.now())
	})
	if err != nil {
		return domain.Program{}, err
	}

	return s.GetProgram(ctx, id)
}

func (s *Service) programRecord(ctx context.Context, in ProgramInput, current Texts) (ports.ProgramRecord, error) {
	v := validate.New()
	texts := in.Translations.apply(v, current, true)

	ids := make([]int64, len(in.Assignments))
	for i, a := range in.Assignments {
		ids[i] = a.TemplateID
	}

	templates, err := s.Store.Existing(ctx, ports.KindWorkoutTemplates, ids)
	if err != nil {
		return ports.ProgramRecord{}, err
	}

	record := ports.ProgramRecord{Name: texts.Name, Description: texts.Description}

	for i, a := range in.Assignments {
		v.Nested("assignments", i, func(v *validate.Validator) {
			v.Check(templates[a.TemplateID], "workout_template_id", apperr.FieldNotFound, "validation.exists",
				map[string]string{"attribute": validate.Attribute("assignments." + strconv.Itoa(i) + ".workout_template_id")})
		})

		record.Schedule = append(record.Schedule, ports.AssignmentRecord{TemplateID: a.TemplateID, Weekday: a.Weekday})
	}

	return record, v.Err()
}

// DeleteProgram removes a program with its assignments and enrollments
// (PHP's behaviour, ADR 0012).
func (s *Service) DeleteProgram(ctx context.Context, id int64) error {
	if _, err := s.Store.Program(ctx, id); err != nil {
		return err
	}

	return s.Store.Delete(ctx, ports.KindPrograms, id)
}

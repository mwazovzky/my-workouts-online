package postgres

import (
	"context"
	"encoding/json"
	"fmt"
	"maps"
	"slices"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// Store implements ports.CatalogStore for catalog sync.
type Store struct {
	tx *postgres.Transactor
}

var _ ports.CatalogStore = (*Store)(nil)

// NewStore returns the catalog sync store.
func NewStore(tx *postgres.Transactor) *Store {
	return &Store{tx: tx}
}

func (s *Store) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(s.tx.Querier(ctx))
}

// Rows implements ports.CatalogStore.
func (s *Store) Rows(ctx context.Context, kind ports.Kind) ([]ports.CatalogRow, error) {
	q := s.q(ctx)

	var (
		rows []ports.CatalogRow
		err  error
	)

	collect := func(id int64, slug *string, name string) {
		rows = append(rows, ports.CatalogRow{ID: id, Slug: slug, Name: name})
	}

	switch kind {
	case ports.KindEquipment:
		var list []sqlcgen.ListEquipmentRowsRow
		if list, err = q.ListEquipmentRows(ctx); err == nil {
			for _, r := range list {
				collect(r.ID, r.Slug, r.Name)
			}
		}
	case ports.KindCategories:
		var list []sqlcgen.ListCategoryRowsRow
		if list, err = q.ListCategoryRows(ctx); err == nil {
			for _, r := range list {
				collect(r.ID, r.Slug, r.Name)
			}
		}
	case ports.KindExercises:
		var list []sqlcgen.ListExerciseRowsRow
		if list, err = q.ListExerciseRows(ctx); err == nil {
			for _, r := range list {
				rows = append(rows, ports.CatalogRow{ID: r.ID, Slug: r.Slug, Name: r.Name, Retired: r.Retired})
			}
		}
	case ports.KindWorkoutTemplates:
		var list []sqlcgen.ListWorkoutTemplateRowsRow
		if list, err = q.ListWorkoutTemplateRows(ctx); err == nil {
			for _, r := range list {
				collect(r.ID, r.Slug, r.Name)
			}
		}
	case ports.KindPrograms:
		var list []sqlcgen.ListProgramRowsRow
		if list, err = q.ListProgramRows(ctx); err == nil {
			for _, r := range list {
				collect(r.ID, r.Slug, r.Name)
			}
		}
	default:
		return nil, fmt.Errorf("catalog: unknown kind %q", kind)
	}

	return rows, postgres.MapError(err)
}

// Equipment implements ports.CatalogStore.
func (s *Store) Equipment(ctx context.Context, id int64) (ports.EquipmentRecord, error) {
	row, err := s.q(ctx).GetEquipmentRecord(ctx, id)
	if err != nil {
		return ports.EquipmentRecord{}, postgres.MapError(err)
	}

	text, err := name(row.Name, row.Translations)

	return ports.EquipmentRecord{Name: text, DifficultyUnit: domain.DifficultyUnit(row.DifficultyUnit)}, err
}

// Category implements ports.CatalogStore.
func (s *Store) Category(ctx context.Context, id int64) (ports.CategoryRecord, error) {
	row, err := s.q(ctx).GetCategoryRecord(ctx, id)
	if err != nil {
		return ports.CategoryRecord{}, postgres.MapError(err)
	}

	text, err := name(row.Name, row.Translations)

	return ports.CategoryRecord{Name: text}, err
}

// Exercise implements ports.CatalogStore.
func (s *Store) Exercise(ctx context.Context, id int64) (ports.ExerciseRecord, error) {
	q := s.q(ctx)

	row, err := q.GetExerciseRecord(ctx, id)
	if err != nil {
		return ports.ExerciseRecord{}, postgres.MapError(err)
	}

	exerciseName, description, err := named(row.Name, row.Description, row.Translations)
	if err != nil {
		return ports.ExerciseRecord{}, err
	}

	categoryIDs, err := q.ListExerciseCategoryIDs(ctx, id)
	if err != nil {
		return ports.ExerciseRecord{}, postgres.MapError(err)
	}

	var rest *int
	if row.RestTimeSeconds != nil {
		seconds := int(*row.RestTimeSeconds)
		rest = &seconds
	}

	return ports.ExerciseRecord{
		Name: exerciseName, Description: description, EquipmentID: row.EquipmentID,
		EffortType: domain.EffortType(row.EffortType), RestTimeSeconds: rest, CategoryIDs: categoryIDs,
	}, nil
}

// Template implements ports.CatalogStore.
func (s *Store) Template(ctx context.Context, id int64) (ports.TemplateRecord, error) {
	q := s.q(ctx)

	row, err := q.GetWorkoutTemplate(ctx, id)
	if err != nil {
		return ports.TemplateRecord{}, postgres.MapError(err)
	}

	templateName, description, err := named(row.Name, row.Description, row.Translations)
	if err != nil {
		return ports.TemplateRecord{}, err
	}

	activities, err := q.ListTemplateActivities(ctx, id)
	if err != nil {
		return ports.TemplateRecord{}, postgres.MapError(err)
	}

	sets, err := q.ListTemplateSets(ctx, id)
	if err != nil {
		return ports.TemplateRecord{}, postgres.MapError(err)
	}

	setsByActivity := map[int64][]ports.SetRecord{}
	for _, set := range sets {
		setsByActivity[set.ActivityID] = append(setsByActivity[set.ActivityID], ports.SetRecord{
			Order: int(set.Order), EffortValue: int(set.EffortValue), DifficultyValue: set.DifficultyValue,
		})
	}

	record := ports.TemplateRecord{Name: templateName, Description: description}
	for _, a := range activities {
		record.Activities = append(record.Activities, ports.ActivityRecord{
			ExerciseID: a.ExerciseID, Order: int(a.Order), Sets: setsByActivity[a.ID],
		})
	}

	return record, nil
}

// Program implements ports.CatalogStore.
func (s *Store) Program(ctx context.Context, id int64) (ports.ProgramRecord, error) {
	q := s.q(ctx)

	row, err := q.GetProgramRecord(ctx, id)
	if err != nil {
		return ports.ProgramRecord{}, postgres.MapError(err)
	}

	programName, description, err := named(row.Name, row.Description, row.Translations)
	if err != nil {
		return ports.ProgramRecord{}, err
	}

	schedule, err := q.ListProgramSchedule(ctx, id)
	if err != nil {
		return ports.ProgramRecord{}, postgres.MapError(err)
	}

	record := ports.ProgramRecord{Name: programName, Description: description}
	for _, a := range schedule {
		record.Schedule = append(record.Schedule, ports.AssignmentRecord{TemplateID: a.TemplateID, Weekday: domain.Weekday(a.Weekday)})
	}

	return record, nil
}

// CreateEquipment implements ports.CatalogStore.
func (s *Store) CreateEquipment(ctx context.Context, slug string, r ports.EquipmentRecord, now time.Time) (int64, error) {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name})
	if err != nil {
		return 0, err
	}

	id, err := s.q(ctx).CreateEquipment(ctx, sqlcgen.CreateEquipmentParams{
		Slug: &slug, Name: r.Name.Source, DifficultyUnit: string(r.DifficultyUnit), Translations: translations, Now: now,
	})

	return id, postgres.MapError(err)
}

// UpdateEquipment implements ports.CatalogStore.
func (s *Store) UpdateEquipment(ctx context.Context, id int64, r ports.EquipmentRecord, now time.Time) error {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name})
	if err != nil {
		return err
	}

	return postgres.MapError(s.q(ctx).UpdateEquipment(ctx, sqlcgen.UpdateEquipmentParams{
		ID: id, Name: r.Name.Source, DifficultyUnit: string(r.DifficultyUnit), Translations: translations, Now: now,
	}))
}

// CreateCategory implements ports.CatalogStore.
func (s *Store) CreateCategory(ctx context.Context, slug string, r ports.CategoryRecord, now time.Time) (int64, error) {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name})
	if err != nil {
		return 0, err
	}

	id, err := s.q(ctx).CreateCategory(ctx, sqlcgen.CreateCategoryParams{Slug: &slug, Name: r.Name.Source, Translations: translations, Now: now})

	return id, postgres.MapError(err)
}

// UpdateCategory implements ports.CatalogStore.
func (s *Store) UpdateCategory(ctx context.Context, id int64, r ports.CategoryRecord, now time.Time) error {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name})
	if err != nil {
		return err
	}

	return postgres.MapError(s.q(ctx).UpdateCategory(ctx, sqlcgen.UpdateCategoryParams{ID: id, Name: r.Name.Source, Translations: translations, Now: now}))
}

// CreateExercise implements ports.CatalogStore.
func (s *Store) CreateExercise(ctx context.Context, slug string, r ports.ExerciseRecord, now time.Time) (int64, error) {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name, "description": r.Description})
	if err != nil {
		return 0, err
	}

	q := s.q(ctx)

	id, err := q.CreateExercise(ctx, sqlcgen.CreateExerciseParams{
		Slug: &slug, Name: r.Name.Source, Description: optional(r.Description.Source), EquipmentID: r.EquipmentID,
		EffortType: string(r.EffortType), RestTimeSeconds: int32Ptr(r.RestTimeSeconds), Translations: translations, Now: now,
	})
	if err != nil {
		return 0, postgres.MapError(err)
	}

	return id, postgres.MapError(q.InsertExerciseCategories(ctx, id, r.CategoryIDs))
}

// UpdateExercise implements ports.CatalogStore.
func (s *Store) UpdateExercise(ctx context.Context, id int64, r ports.ExerciseRecord, now time.Time) error {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name, "description": r.Description})
	if err != nil {
		return err
	}

	q := s.q(ctx)

	if err := q.UpdateExercise(ctx, sqlcgen.UpdateExerciseParams{
		ID: id, Name: r.Name.Source, Description: optional(r.Description.Source), EquipmentID: r.EquipmentID,
		EffortType: string(r.EffortType), RestTimeSeconds: int32Ptr(r.RestTimeSeconds), Translations: translations, Now: now,
	}); err != nil {
		return postgres.MapError(err)
	}

	if err := q.DeleteExerciseCategories(ctx, id); err != nil {
		return postgres.MapError(err)
	}

	return postgres.MapError(q.InsertExerciseCategories(ctx, id, r.CategoryIDs))
}

// CreateTemplate implements ports.CatalogStore.
func (s *Store) CreateTemplate(ctx context.Context, slug string, r ports.TemplateRecord, now time.Time) (int64, error) {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name, "description": r.Description})
	if err != nil {
		return 0, err
	}

	id, err := s.q(ctx).CreateWorkoutTemplate(ctx, sqlcgen.CreateWorkoutTemplateParams{
		Slug: &slug, Name: r.Name.Source, Description: optional(r.Description.Source), Translations: translations, Now: now,
	})
	if err != nil {
		return 0, postgres.MapError(err)
	}

	return id, s.insertActivities(ctx, id, r.Activities, now)
}

// UpdateTemplate implements ports.CatalogStore.
func (s *Store) UpdateTemplate(ctx context.Context, id int64, r ports.TemplateRecord, now time.Time) error {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name, "description": r.Description})
	if err != nil {
		return err
	}

	q := s.q(ctx)

	if err := q.UpdateWorkoutTemplate(ctx, sqlcgen.UpdateWorkoutTemplateParams{
		ID: id, Name: r.Name.Source, Description: optional(r.Description.Source), Translations: translations, Now: now,
	}); err != nil {
		return postgres.MapError(err)
	}

	if err := q.DeleteTemplateActivities(ctx, id); err != nil {
		return postgres.MapError(err)
	}

	return s.insertActivities(ctx, id, r.Activities, now)
}

func (s *Store) insertActivities(ctx context.Context, templateID int64, activities []ports.ActivityRecord, now time.Time) error {
	q := s.q(ctx)

	for _, a := range activities {
		activityID, err := q.InsertTemplateActivity(ctx, sqlcgen.InsertTemplateActivityParams{
			TemplateID: templateID, ExerciseID: a.ExerciseID, Position: int32(a.Order), Now: now, //nolint:gosec // orders are small
		})
		if err != nil {
			return postgres.MapError(err)
		}

		for _, set := range a.Sets {
			if err := q.InsertTemplateSet(ctx, sqlcgen.InsertTemplateSetParams{
				ActivityID: activityID, Position: int32(set.Order), EffortValue: int32(set.EffortValue), //nolint:gosec // validated small integers
				DifficultyValue: set.DifficultyValue, Now: now,
			}); err != nil {
				return postgres.MapError(err)
			}
		}
	}

	return nil
}

// CreateProgram implements ports.CatalogStore.
func (s *Store) CreateProgram(ctx context.Context, slug string, r ports.ProgramRecord, now time.Time) (int64, error) {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name, "description": r.Description})
	if err != nil {
		return 0, err
	}

	id, err := s.q(ctx).CreateProgram(ctx, sqlcgen.CreateProgramParams{
		Slug: &slug, Name: r.Name.Source, Description: optional(r.Description.Source), Translations: translations, Now: now,
	})
	if err != nil {
		return 0, postgres.MapError(err)
	}

	return id, s.insertSchedule(ctx, id, r.Schedule)
}

// UpdateProgram implements ports.CatalogStore.
func (s *Store) UpdateProgram(ctx context.Context, id int64, r ports.ProgramRecord, now time.Time) error {
	translations, err := encode(map[string]domain.LocalizedText{"name": r.Name, "description": r.Description})
	if err != nil {
		return err
	}

	q := s.q(ctx)

	if err := q.UpdateProgram(ctx, sqlcgen.UpdateProgramParams{
		ID: id, Name: r.Name.Source, Description: optional(r.Description.Source), Translations: translations, Now: now,
	}); err != nil {
		return postgres.MapError(err)
	}

	if err := q.DeleteProgramAssignments(ctx, id); err != nil {
		return postgres.MapError(err)
	}

	return s.insertSchedule(ctx, id, r.Schedule)
}

func (s *Store) insertSchedule(ctx context.Context, programID int64, schedule []ports.AssignmentRecord) error {
	q := s.q(ctx)

	for _, a := range schedule {
		if err := q.InsertProgramAssignment(ctx, sqlcgen.InsertProgramAssignmentParams{
			ProgramID: programID, TemplateID: a.TemplateID, Weekday: string(a.Weekday),
		}); err != nil {
			return postgres.MapError(err)
		}
	}

	return nil
}

// SetSlug implements ports.CatalogStore.
func (s *Store) SetSlug(ctx context.Context, kind ports.Kind, id int64, slug string) error {
	q := s.q(ctx)

	var err error

	switch kind {
	case ports.KindEquipment:
		err = q.SetEquipmentSlug(ctx, id, &slug)
	case ports.KindCategories:
		err = q.SetCategorySlug(ctx, id, &slug)
	case ports.KindExercises:
		err = q.SetExerciseSlug(ctx, id, &slug)
	case ports.KindWorkoutTemplates:
		err = q.SetWorkoutTemplateSlug(ctx, id, &slug)
	case ports.KindPrograms:
		err = q.SetProgramSlug(ctx, id, &slug)
	default:
		return fmt.Errorf("catalog: unknown kind %q", kind)
	}

	return postgres.MapError(err)
}

// RetireExercise implements ports.CatalogStore.
func (s *Store) RetireExercise(ctx context.Context, id int64, now time.Time) error {
	return postgres.MapError(s.q(ctx).RetireExercise(ctx, id, &now))
}

// Delete implements ports.CatalogStore.
func (s *Store) Delete(ctx context.Context, kind ports.Kind, id int64) error {
	q := s.q(ctx)

	var err error

	switch kind {
	case ports.KindEquipment:
		err = q.DeleteEquipment(ctx, id)
	case ports.KindCategories:
		err = q.DeleteCategory(ctx, id)
	case ports.KindWorkoutTemplates:
		err = q.DeleteWorkoutTemplate(ctx, id)
	case ports.KindPrograms:
		err = q.DeleteProgram(ctx, id)
	case ports.KindExercises:
		return fmt.Errorf("catalog: exercises are retired, not deleted")
	default:
		return fmt.Errorf("catalog: unknown kind %q", kind)
	}

	return postgres.MapError(err)
}

// InUse implements ports.CatalogStore.
func (s *Store) InUse(ctx context.Context, kind ports.Kind, id int64) (bool, error) {
	q := s.q(ctx)

	var (
		inUse bool
		err   error
	)

	switch kind {
	case ports.KindEquipment:
		inUse, err = q.EquipmentInUse(ctx, id)
	case ports.KindCategories:
		inUse, err = q.CategoryInUse(ctx, id)
	case ports.KindWorkoutTemplates:
		inUse, err = q.WorkoutTemplateInUse(ctx, id)
	case ports.KindPrograms:
		inUse, err = q.ProgramHasEnrollments(ctx, id)
	case ports.KindExercises:
		return false, nil
	default:
		return false, fmt.Errorf("catalog: unknown kind %q", kind)
	}

	return inUse, postgres.MapError(err)
}

// EquipmentExerciseIDs implements ports.CatalogStore.
func (s *Store) EquipmentExerciseIDs(ctx context.Context, equipmentID int64) ([]int64, error) {
	ids, err := s.q(ctx).ListEquipmentExerciseIDs(ctx, equipmentID)

	return ids, postgres.MapError(err)
}

// HasLoggedActivities implements ports.CatalogStore.
func (s *Store) HasLoggedActivities(ctx context.Context, exerciseIDs []int64) (bool, error) {
	if len(exerciseIDs) == 0 {
		return false, nil
	}

	logged, err := s.q(ctx).HasLoggedActivities(ctx, exerciseIDs)

	return logged, postgres.MapError(err)
}

// encode builds the translations column from a row's fields: every
// non-English value, keyed by locale and then field (ADR 0004).
func encode(fields map[string]domain.LocalizedText) ([]byte, error) {
	out := translations{}

	for _, field := range slices.Sorted(maps.Keys(fields)) {
		for locale, value := range fields[field].Other {
			if value == "" {
				continue
			}

			if out[locale] == nil {
				out[locale] = map[string]string{}
			}

			out[locale][field] = value
		}
	}

	data, err := json.Marshal(out)
	if err != nil {
		return nil, fmt.Errorf("catalog: encode translations: %w", err)
	}

	return data, nil
}

// optional is a nullable column: an empty source text is stored as NULL, as
// PHP stores a blank one.
func optional(s string) *string {
	if s == "" {
		return nil
	}

	return &s
}

func int32Ptr(v *int) *int32 {
	if v == nil {
		return nil
	}

	n := int32(*v) //nolint:gosec // rest times are validated to 0..3600

	return &n
}

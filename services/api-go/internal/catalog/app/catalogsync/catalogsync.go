// Package catalogsync applies the shared YAML catalog to the database, as
// PHP's catalog:sync does (ADR 0009). Validate checks the data files against
// every rule of PHP's CatalogValidator; Syncer.Run then, in one transaction,
// matches each record to a row by slug or adopts an unmanaged row by its
// English name or a previous name, brings managed rows in line with the files
// (the files win), leaves rows created in the admin UI alone, and retires
// what retired.yaml lists unless something still uses it.
package catalogsync

import (
	"context"
	"errors"
	"fmt"
	"maps"
	"slices"
	"strings"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/txn"
)

// InvalidError is a catalog that cannot be read (Load) or breaks validation
// rules (Violations); nothing is written.
type InvalidError struct {
	Load       error
	Violations []Violation
}

func (e *InvalidError) Error() string {
	if e.Load != nil {
		return "the catalog data is invalid: " + e.Load.Error()
	}

	return fmt.Sprintf("the catalog data is invalid (%d violation(s))", len(e.Violations))
}

// Unwrap returns the load error, if any.
func (e *InvalidError) Unwrap() error { return e.Load }

// Messages lists what is wrong, one line each.
func (e *InvalidError) Messages() []string {
	if e.Load != nil {
		return []string{e.Load.Error()}
	}

	messages := make([]string, len(e.Violations))
	for i, v := range e.Violations {
		messages[i] = v.Message
	}

	return messages
}

// Options are the sync's flags.
type Options struct {
	// DryRun reports what would change and writes nothing.
	DryRun bool
	// Strict writes nothing when the sync raises warnings.
	Strict bool
}

// Deps are the Syncer's collaborators.
type Deps struct {
	Source ports.CatalogSource
	Store  ports.CatalogStore
	Tx     txn.Transactor
	Clock  clock.Clock
	// Locales are the supported locales; every record needs each of them.
	Locales []string
}

// Syncer runs the catalog sync.
type Syncer struct {
	Deps
}

// New returns a Syncer.
func New(deps Deps) *Syncer {
	return &Syncer{Deps: deps}
}

// errRollback ends the transaction without committing (dry run, or strict
// mode with warnings).
var errRollback = errors.New("catalog sync: roll back")

// Run loads, validates and applies the catalog. An invalid catalog is an
// *InvalidError; a failure part way through rolls everything back.
func (s *Syncer) Run(ctx context.Context, opts Options) (*Report, error) {
	raw, err := s.Source.Load()
	if err != nil {
		return nil, &InvalidError{Load: err}
	}

	if violations := Validate(raw, s.Locales); len(violations) > 0 {
		return nil, &InvalidError{Violations: violations}
	}

	report := newReport(opts.DryRun)

	err = s.Tx.InTx(ctx, func(ctx context.Context) error {
		run := &run{store: s.Store, report: report, now: s.Clock.Now(), locales: s.Locales, ids: map[ports.Kind]map[string]int64{}}
		if err := run.apply(ctx, build(raw)); err != nil {
			return err
		}

		if opts.DryRun || (opts.Strict && len(report.Warnings) > 0) {
			return errRollback
		}

		return nil
	})

	switch {
	case errors.Is(err, errRollback):
		return report, nil
	case err != nil:
		return nil, err
	}

	report.Committed = true

	return report, nil
}

// run is one sync's state: the row index and the ids of synced records.
type run struct {
	store   ports.CatalogStore
	report  *Report
	now     time.Time
	locales []string
	index   map[ports.Kind]*rowIndex
	ids     map[ports.Kind]map[string]int64
}

// rowIndex holds existing rows: managed rows by slug, unmanaged rows by
// normalised English name, each name's rows by id.
type rowIndex struct {
	slugs map[string]ports.CatalogRow
	names map[string]map[int64]ports.CatalogRow
}

func (r *run) apply(ctx context.Context, d definition) error {
	r.index = map[ports.Kind]*rowIndex{}

	for _, kind := range ports.Kinds {
		rows, err := r.store.Rows(ctx, kind)
		if err != nil {
			return err
		}

		index := &rowIndex{slugs: map[string]ports.CatalogRow{}, names: map[string]map[int64]ports.CatalogRow{}}

		for _, row := range rows {
			if row.Slug != nil {
				index.slugs[*row.Slug] = row

				continue
			}

			if name := normaliseName(row.Name); name != "" {
				if index.names[name] == nil {
					index.names[name] = map[int64]ports.CatalogRow{}
				}

				index.names[name][row.ID] = row
			}
		}

		r.index[kind] = index
		r.ids[kind] = map[string]int64{}
	}

	for _, step := range []func(context.Context, definition) error{
		r.equipment, r.categories, r.exercises, r.templates, r.programs, r.retire,
	} {
		if err := step(ctx, d); err != nil {
			return err
		}
	}

	return nil
}

// find returns the row of a record: by slug, otherwise an unmanaged row
// carrying one of names, preferring a row that is not retired, then the
// lowest id. A row is claimed once.
func (r *run) find(kind ports.Kind, slug string, names []string) (ports.CatalogRow, bool) {
	index := r.index[kind]
	if row, ok := index.slugs[slug]; ok {
		return row, true
	}

	candidates := map[int64]ports.CatalogRow{}
	for _, name := range names {
		maps.Copy(candidates, index.names[normaliseName(name)])
	}

	if len(candidates) == 0 {
		return ports.CatalogRow{}, false
	}

	ids := slices.Sorted(maps.Keys(candidates))
	chosen := candidates[ids[0]]

	for _, id := range ids {
		if !candidates[id].Retired {
			chosen = candidates[id]

			break
		}
	}

	if len(ids) > 1 {
		idList := make([]string, len(ids))
		for i, id := range ids {
			idList[i] = fmt.Sprint(id)
		}

		r.report.warn(fmt.Sprintf("%s [%s]: %d rows match by name (ids %s); using id %d.",
			kind, slug, len(ids), strings.Join(idList, ", "), chosen.ID))
	}

	for _, rows := range index.names {
		delete(rows, chosen.ID)
	}

	index.slugs[slug] = chosen

	return chosen, true
}

// idOf is the id a synced record got; the validator guarantees it exists.
func (r *run) idOf(kind ports.Kind, slug string) int64 {
	return r.ids[kind][slug]
}

// persist creates the record, or brings the existing row in line with the
// data files and reports what changed.
func (r *run) persist(ctx context.Context, kind ports.Kind, slug string, existing *ports.CatalogRow, changed []string,
	create func() (int64, error), update func(int64) error,
) error {
	if existing == nil {
		id, err := create()
		if err != nil {
			return err
		}

		r.ids[kind][slug] = id
		r.index[kind].slugs[slug] = ports.CatalogRow{ID: id, Slug: &slug}
		r.report.record(kind, Created, slug, nil)

		return nil
	}

	if existing.Retired {
		changed = append(changed, "restored")
	}

	if len(changed) > 0 {
		if err := update(existing.ID); err != nil {
			return err
		}
	}

	adopted := existing.Slug == nil
	if adopted {
		if err := r.store.SetSlug(ctx, kind, existing.ID, slug); err != nil {
			return err
		}
	}

	r.ids[kind][slug] = existing.ID

	outcome := Unchanged

	switch {
	case adopted:
		outcome = Adopted
	case len(changed) > 0:
		outcome = Updated
	}

	r.report.record(kind, outcome, slug, changed)

	return nil
}

// textChanges lists the translated fields whose stored value differs from
// the files, as "name (en)", in locale and then field order.
func (r *run) textChanges(c common, fields []string, current map[string]domain.LocalizedText) []string {
	var changed []string

	for _, locale := range r.locales {
		for _, field := range fields {
			want, ok := c.translations[locale][field]
			if !ok {
				continue
			}

			stored := current[field]

			have := stored.Other[i18n.Locale(locale)]
			if locale == string(i18n.EN) {
				have = stored.Source
			}

			if have != want {
				changed = append(changed, fmt.Sprintf("%s (%s)", field, locale))
			}
		}
	}

	return changed
}

func (r *run) equipment(ctx context.Context, d definition) error {
	for _, def := range d.equipment {
		record := ports.EquipmentRecord{Name: def.text("name"), DifficultyUnit: def.unit}

		row, found := r.find(ports.KindEquipment, def.slug, def.englishNames())

		var changed []string

		if found {
			current, err := r.store.Equipment(ctx, row.ID)
			if err != nil {
				return err
			}

			if current.DifficultyUnit != def.unit {
				changed = append(changed, "difficulty_unit")

				if err := r.warnUnitChange(ctx, def, row.ID, current.DifficultyUnit); err != nil {
					return err
				}
			}

			changed = append(changed, r.textChanges(def.common, []string{"name"}, map[string]domain.LocalizedText{"name": current.Name})...)
		}

		err := r.persist(ctx, ports.KindEquipment, def.slug, existing(row, found), changed,
			func() (int64, error) { return r.store.CreateEquipment(ctx, def.slug, record, r.now) },
			func(id int64) error { return r.store.UpdateEquipment(ctx, id, record, r.now) })
		if err != nil {
			return err
		}
	}

	return nil
}

// warnUnitChange warns when equipment whose exercises were logged changes
// its unit: stored values carry no unit, so the logs would be relabelled.
func (r *run) warnUnitChange(ctx context.Context, def equipmentDef, id int64, from domain.DifficultyUnit) error {
	exerciseIDs, err := r.store.EquipmentExerciseIDs(ctx, id)
	if err != nil {
		return err
	}

	logged, err := r.store.HasLoggedActivities(ctx, exerciseIDs)
	if err != nil || !logged {
		return err
	}

	r.report.warn(fmt.Sprintf("equipment [%s]: difficulty unit changes from %s to %s, which relabels logged workouts.", def.slug, from, def.unit))

	return nil
}

func (r *run) categories(ctx context.Context, d definition) error {
	for _, def := range d.categories {
		record := ports.CategoryRecord{Name: def.text("name")}

		row, found := r.find(ports.KindCategories, def.slug, def.englishNames())

		var changed []string

		if found {
			current, err := r.store.Category(ctx, row.ID)
			if err != nil {
				return err
			}

			changed = r.textChanges(def.common, []string{"name"}, map[string]domain.LocalizedText{"name": current.Name})
		}

		err := r.persist(ctx, ports.KindCategories, def.slug, existing(row, found), changed,
			func() (int64, error) { return r.store.CreateCategory(ctx, def.slug, record, r.now) },
			func(id int64) error { return r.store.UpdateCategory(ctx, id, record, r.now) })
		if err != nil {
			return err
		}
	}

	return nil
}

func (r *run) exercises(ctx context.Context, d definition) error {
	units := map[string]domain.DifficultyUnit{}
	for _, e := range d.equipment {
		units[e.slug] = e.unit
	}

	for _, def := range d.exercises {
		categoryIDs := make([]int64, len(def.categories))
		for i, slug := range def.categories {
			categoryIDs[i] = r.idOf(ports.KindCategories, slug)
		}

		slices.Sort(categoryIDs)

		record := ports.ExerciseRecord{
			Name: def.text("name"), Description: def.text("description"),
			EquipmentID: r.idOf(ports.KindEquipment, def.equipment), EffortType: def.effortType,
			RestTimeSeconds: def.restTimeSeconds, CategoryIDs: categoryIDs,
		}

		row, found := r.find(ports.KindExercises, def.slug, def.englishNames())

		var changed []string

		if found {
			current, err := r.store.Exercise(ctx, row.ID)
			if err != nil {
				return err
			}

			if err := r.warnMeasurementChange(ctx, def, row.ID, current, record.EquipmentID, units[def.equipment]); err != nil {
				return err
			}

			if current.EquipmentID != record.EquipmentID {
				changed = append(changed, "equipment")
			}

			if current.EffortType != record.EffortType {
				changed = append(changed, "effort_type")
			}

			if !equalPtr(current.RestTimeSeconds, record.RestTimeSeconds) {
				changed = append(changed, "rest_time_seconds")
			}

			if !slices.Equal(current.CategoryIDs, record.CategoryIDs) {
				changed = append(changed, "categories")
			}

			changed = append(changed, r.textChanges(def.common, []string{"name", "description"},
				map[string]domain.LocalizedText{"name": current.Name, "description": current.Description})...)
		}

		err := r.persist(ctx, ports.KindExercises, def.slug, existing(row, found), changed,
			func() (int64, error) { return r.store.CreateExercise(ctx, def.slug, record, r.now) },
			func(id int64) error { return r.store.UpdateExercise(ctx, id, record, r.now) })
		if err != nil {
			return err
		}
	}

	return nil
}

// warnMeasurementChange warns when a logged exercise changes its effort type
// or moves to equipment with another unit.
func (r *run) warnMeasurementChange(ctx context.Context, def exerciseDef, id int64, current ports.ExerciseRecord,
	equipmentID int64, unit domain.DifficultyUnit,
) error {
	var changes []string

	if current.EffortType != def.effortType {
		changes = append(changes, fmt.Sprintf("effort type changes from %s to %s", current.EffortType, def.effortType))
	}

	if current.EquipmentID != equipmentID {
		equipment, err := r.store.Equipment(ctx, current.EquipmentID)
		if err != nil {
			return err
		}

		if equipment.DifficultyUnit != unit {
			changes = append(changes, fmt.Sprintf("difficulty unit changes from %s to %s", equipment.DifficultyUnit, unit))
		}
	}

	if len(changes) == 0 {
		return nil
	}

	logged, err := r.store.HasLoggedActivities(ctx, []int64{id})
	if err != nil || !logged {
		return err
	}

	r.report.warn(fmt.Sprintf("exercises [%s]: %s, which relabels logged workouts.", def.slug, strings.Join(changes, " and ")))

	return nil
}

func (r *run) templates(ctx context.Context, d definition) error {
	for _, def := range d.templates {
		record := ports.TemplateRecord{Name: def.text("name"), Description: def.text("description")}

		for i, activity := range def.activities {
			a := ports.ActivityRecord{ExerciseID: r.idOf(ports.KindExercises, activity.exercise), Order: i + 1}
			for j, set := range activity.sets {
				a.Sets = append(a.Sets, ports.SetRecord{Order: j + 1, EffortValue: set.effort, DifficultyValue: set.difficulty})
			}

			record.Activities = append(record.Activities, a)
		}

		row, found := r.find(ports.KindWorkoutTemplates, def.slug, def.englishNames())

		var changed []string

		if found {
			current, err := r.store.Template(ctx, row.ID)
			if err != nil {
				return err
			}

			if !slices.EqualFunc(current.Activities, record.Activities, equalActivity) {
				changed = append(changed, "activities")
			}

			changed = append(changed, r.textChanges(def.common, []string{"name", "description"},
				map[string]domain.LocalizedText{"name": current.Name, "description": current.Description})...)
		}

		err := r.persist(ctx, ports.KindWorkoutTemplates, def.slug, existing(row, found), changed,
			func() (int64, error) { return r.store.CreateTemplate(ctx, def.slug, record, r.now) },
			func(id int64) error { return r.store.UpdateTemplate(ctx, id, record, r.now) })
		if err != nil {
			return err
		}
	}

	return nil
}

func (r *run) programs(ctx context.Context, d definition) error {
	for _, def := range d.programs {
		record := ports.ProgramRecord{Name: def.text("name"), Description: def.text("description")}
		for _, entry := range def.schedule {
			record.Schedule = append(record.Schedule, ports.AssignmentRecord{
				TemplateID: r.idOf(ports.KindWorkoutTemplates, entry.template), Weekday: entry.weekday,
			})
		}

		row, found := r.find(ports.KindPrograms, def.slug, def.englishNames())

		var changed []string

		if found {
			current, err := r.store.Program(ctx, row.ID)
			if err != nil {
				return err
			}

			if !slices.Equal(current.Schedule, record.Schedule) {
				changed = append(changed, "schedule")
			}

			changed = append(changed, r.textChanges(def.common, []string{"name", "description"},
				map[string]domain.LocalizedText{"name": current.Name, "description": current.Description})...)
		}

		err := r.persist(ctx, ports.KindPrograms, def.slug, existing(row, found), changed,
			func() (int64, error) { return r.store.CreateProgram(ctx, def.slug, record, r.now) },
			func(id int64) error { return r.store.UpdateProgram(ctx, id, record, r.now) })
		if err != nil {
			return err
		}
	}

	return nil
}

// blockers are the warnings for a retired record something still uses.
var blockers = map[ports.Kind]string{
	ports.KindEquipment:        "exercises still use it",
	ports.KindCategories:       "exercises still belong to it",
	ports.KindWorkoutTemplates: "a program still uses it",
	ports.KindPrograms:         "users are enrolled in it",
}

// retire removes what retired.yaml lists, in reverse dependency order, so a
// retired program releases its templates before they are checked. Exercises
// are soft-retired; the other kinds are deleted when nothing uses them.
func (r *run) retire(ctx context.Context, d definition) error {
	for _, kind := range slices.Backward(ports.Kinds) {
		for _, entry := range d.retired[kind] {
			row, found := r.find(kind, entry.slug, entry.names)
			if !found {
				continue
			}

			if kind == ports.KindExercises {
				if err := r.retireExercise(ctx, entry, row); err != nil {
					return err
				}

				continue
			}

			inUse, err := r.store.InUse(ctx, kind, row.ID)
			if err != nil {
				return err
			}

			if inUse {
				r.report.warn(fmt.Sprintf("%s [%s]: not retired because %s.", kind, entry.slug, blockers[kind]))

				continue
			}

			if err := r.store.Delete(ctx, kind, row.ID); err != nil {
				return err
			}

			r.report.record(kind, Retired, entry.slug, nil)
		}
	}

	return nil
}

func (r *run) retireExercise(ctx context.Context, entry retiredDef, row ports.CatalogRow) error {
	if row.Slug == nil {
		if err := r.store.SetSlug(ctx, ports.KindExercises, row.ID, entry.slug); err != nil {
			return err
		}
	}

	if row.Retired {
		return nil
	}

	if err := r.store.RetireExercise(ctx, row.ID, r.now); err != nil {
		return err
	}

	r.report.record(ports.KindExercises, Retired, entry.slug, nil)

	return nil
}

func existing(row ports.CatalogRow, found bool) *ports.CatalogRow {
	if !found {
		return nil
	}

	return &row
}

func equalActivity(a, b ports.ActivityRecord) bool {
	return a.ExerciseID == b.ExerciseID && a.Order == b.Order && slices.EqualFunc(a.Sets, b.Sets, func(x, y ports.SetRecord) bool {
		return x.Order == y.Order && x.EffortValue == y.EffortValue && equalPtr(x.DifficultyValue, y.DifficultyValue)
	})
}

func equalPtr[T comparable](a, b *T) bool {
	if a == nil || b == nil {
		return a == b
	}

	return *a == *b
}

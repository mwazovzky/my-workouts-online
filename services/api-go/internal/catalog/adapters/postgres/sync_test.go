package postgres_test

import (
	"context"
	"errors"
	"os"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"github.com/jackc/pgx/v5/pgxpool"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/catalogfile"
	catalogpg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app/catalogsync"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

// The Go counterparts of tests/Feature/Catalog/CatalogSyncTest.php: the
// same fixture catalog (catalog/testdata/valid is PHP's tests/Fixtures/catalog
// exported), the same scenarios and the same expected counts and messages.

const validCatalog = "../../../../../../catalog/testdata/valid"

// fixedSource returns a catalog a test has edited.
type fixedSource struct{ catalog ports.RawCatalog }

func (s fixedSource) Load() (ports.RawCatalog, error) { return s.catalog, nil }

// failingStore fails CreateProgram, the last write of a fresh sync.
type failingStore struct{ ports.CatalogStore }

func (failingStore) CreateProgram(context.Context, *string, ports.ProgramRecord, time.Time) (int64, error) {
	return 0, errors.New("the database went away")
}

type syncFixture struct {
	pool  *pgxpool.Pool
	tx    *postgres.Transactor
	store ports.CatalogStore
	clock *clock.Fake
}

func newSyncFixture(t *testing.T) *syncFixture {
	t.Helper()

	pool := testdb.New(t)
	tx := postgres.NewTransactor(pool)

	return &syncFixture{pool: pool, tx: tx, store: catalogpg.NewStore(tx), clock: clock.NewFake(time.Date(2026, 10, 12, 9, 0, 0, 0, time.UTC))}
}

// catalog loads the fixture catalog, fresh for each edit.
func catalog(t *testing.T) ports.RawCatalog {
	t.Helper()

	c, err := catalogfile.New(os.DirFS(validCatalog)).Load()
	if err != nil {
		t.Fatal(err)
	}

	return c
}

func (f *syncFixture) run(t *testing.T, c ports.RawCatalog, opts catalogsync.Options) (*catalogsync.Report, error) {
	t.Helper()

	return catalogsync.New(catalogsync.Deps{
		Source: fixedSource{c}, Store: f.store, Tx: f.tx, Clock: f.clock, Locales: []string{"en", "ru"},
	}).Run(t.Context(), opts)
}

// sync syncs c (the fixture catalog when nil) and fails the test on error.
func (f *syncFixture) sync(t *testing.T, c *ports.RawCatalog, opts ...catalogsync.Options) *catalogsync.Report {
	t.Helper()

	if c == nil {
		fresh := catalog(t)
		c = &fresh
	}

	var o catalogsync.Options
	if len(opts) > 0 {
		o = opts[0]
	}

	report, err := f.run(t, *c, o)
	if err != nil {
		t.Fatal(err)
	}

	return report
}

func (f *syncFixture) exec(t *testing.T, sql string, args ...any) {
	t.Helper()

	if _, err := f.pool.Exec(t.Context(), sql, args...); err != nil {
		t.Fatal(err)
	}
}

func (f *syncFixture) id(t *testing.T, sql string, args ...any) int64 {
	t.Helper()

	var id int64
	if err := f.pool.QueryRow(t.Context(), sql, args...).Scan(&id); err != nil {
		t.Fatal(err)
	}

	return id
}

func (f *syncFixture) count(t *testing.T, table string) int64 {
	t.Helper()

	return f.id(t, "SELECT count(*) FROM "+table)
}

func (f *syncFixture) strings(t *testing.T, sql string, args ...any) []string {
	t.Helper()

	rows, err := f.pool.Query(t.Context(), sql, args...)
	if err != nil {
		t.Fatal(err)
	}
	defer rows.Close()

	var out []string

	for rows.Next() {
		var s *string
		if err := rows.Scan(&s); err != nil {
			t.Fatal(err)
		}

		if s == nil {
			out = append(out, "<nil>")
		} else {
			out = append(out, *s)
		}
	}

	if err := rows.Err(); err != nil {
		t.Fatal(err)
	}

	return out
}

// unmanagedEquipment is equipment no catalog record matches by name.
func (f *syncFixture) unmanagedEquipment(t *testing.T) int64 {
	t.Helper()

	return f.id(t, "INSERT INTO equipment (name, difficulty_unit) VALUES ('Garage Rack ' || (SELECT count(*) + 1 FROM equipment), 'kilograms') RETURNING id")
}

func (f *syncFixture) unmanagedExercise(t *testing.T, name string, equipmentID ...int64) int64 {
	t.Helper()

	equipment := int64(0)
	if len(equipmentID) > 0 {
		equipment = equipmentID[0]
	} else {
		equipment = f.unmanagedEquipment(t)
	}

	return f.id(t, `INSERT INTO exercises (name, description, equipment_id, effort_type, rest_time_seconds, updated_at)
		VALUES ($1, 'Entered by hand.', $2, 'repetitions', 120, '2026-10-01 00:00:00+00') RETURNING id`, name, equipment)
}

// logWorkoutWith logs a workout of the exercise and returns the activity id.
func (f *syncFixture) logWorkoutWith(t *testing.T, exerciseID int64) int64 {
	t.Helper()

	user := f.id(t, "INSERT INTO users (name, email, password_hash) VALUES ('Ada', 'ada'||(SELECT count(*) FROM users)||'@example.com', 'x') RETURNING id")
	workout := f.id(t, "INSERT INTO workouts (user_id, name) VALUES ($1, 'Logged') RETURNING id", user)
	activity := f.id(t, `INSERT INTO workout_activities (workout_id, exercise_id, "order") VALUES ($1, $2, 1) RETURNING id`, workout, exerciseID)
	f.exec(t, `INSERT INTO workout_sets (activity_id, "order", effort_value, difficulty_value) VALUES ($1, 1, 5, 50)`, activity)

	return activity
}

func (f *syncFixture) slugID(t *testing.T, table, slug string) int64 {
	t.Helper()

	return f.id(t, "SELECT id FROM "+table+" WHERE slug = $1", slug)
}

// record returns the record of kind with slug in c, to edit.
func record(c ports.RawCatalog, kind ports.Kind, slug string) map[string]any {
	for _, r := range recordsOf(c, kind) {
		if m := r.(map[string]any); m["slug"] == slug {
			return m
		}
	}

	panic("no " + string(kind) + " " + slug)
}

func recordsOf(c ports.RawCatalog, kind ports.Kind) []any {
	switch kind {
	case ports.KindEquipment:
		return c.Equipment
	case ports.KindCategories:
		return c.Categories
	case ports.KindExercises:
		return c.Exercises
	case ports.KindWorkoutTemplates:
		return c.WorkoutTemplates
	case ports.KindPrograms:
		return c.Programs
	default:
		return nil
	}
}

func without(records []any, slug string) []any {
	return slices.DeleteFunc(slices.Clone(records), func(r any) bool { return r.(map[string]any)["slug"] == slug })
}

func retire(c *ports.RawCatalog, kind ports.Kind, slug string, names ...string) {
	list, _ := c.Retired[string(kind)].([]any)

	nameList := make([]any, len(names))
	for i, n := range names {
		nameList[i] = n
	}

	c.Retired[string(kind)] = append(list, map[string]any{"slug": slug, "names": nameList})
}

// withoutBenchPress drops the bench press and full-body's first activity.
func withoutBenchPress(t *testing.T) ports.RawCatalog {
	t.Helper()

	c := catalog(t)
	c.Exercises = without(c.Exercises, "bench-press")
	fullBody := record(c, ports.KindWorkoutTemplates, "full-body")
	fullBody["activities"] = fullBody["activities"].([]any)[1:]

	return c
}

func strengthOnly() map[string]any {
	return map[string]any{
		"slug": "strength-only",
		"translations": map[string]any{
			"en": map[string]any{"name": "Strength Only", "description": "One strength day."},
			"ru": map[string]any{"name": "Только сила", "description": "Один силовой день."},
		},
		"schedule":       []any{map[string]any{"weekday": "monday", "template": "full-body"}},
		"previous_names": []any{},
	}
}

func TestSyncCreatesTheCatalogInAnEmptyDatabase(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	report := f.sync(t, nil)

	got := map[ports.Kind]int{}
	for _, kind := range ports.Kinds {
		got[kind] = report.Count(kind, catalogsync.Created)
	}

	want := map[ports.Kind]int{"equipment": 3, "categories": 3, "exercises": 4, "workout_templates": 2, "programs": 1}
	if diff := cmp.Diff(want, got); diff != "" || !report.Committed || len(report.Warnings) > 0 {
		t.Errorf("created (-want +got):\n%s\ncommitted %v, warnings %v", diff, report.Committed, report.Warnings)
	}

	if f.count(t, "equipment") != 3 || f.count(t, "exercises") != 4 {
		t.Error("rows were not written")
	}
}

func TestSyncStoresExerciseAttributesTranslationsAndCategories(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	got := f.strings(t, `SELECT concat_ws('|', eq.slug, e.effort_type, e.rest_time_seconds, e.name,
			e.translations #>> '{ru,name}', e.translations #>> '{ru,description}')
		FROM exercises e JOIN equipment eq ON eq.id = e.equipment_id WHERE e.slug = 'bench-press'`)
	want := []string{"barbell|repetitions|120|Bench Press|Жим лёжа|Жим штанги от груди."}

	if !slices.Equal(got, want) {
		t.Errorf("bench-press = %q", got)
	}

	if got := f.strings(t, `SELECT c.slug FROM category_exercise ce JOIN categories c ON c.id = ce.category_id
		JOIN exercises e ON e.id = ce.exercise_id WHERE e.slug = 'bench-press'`); !slices.Equal(got, []string{"chest"}) {
		t.Errorf("categories = %q", got)
	}
}

func TestSyncStoresTemplateActivitiesAndSetsInOrder(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	got := f.strings(t, `SELECT concat_ws(':', e.slug, a."order",
			(SELECT string_agg(s.effort_value || '/' || coalesce(s.difficulty_value::text, '-'), ',' ORDER BY s."order")
			 FROM template_sets s WHERE s.activity_id = a.id))
		FROM template_activities a JOIN exercises e ON e.id = a.exercise_id
		JOIN workout_templates t ON t.id = a.template_id
		WHERE t.slug = $1 ORDER BY a."order"`, "full-body")
	want := []string{"bench-press:1:8/-,8/-,8/-", "squat:2:10/-,8/-", "push-up:3:15/-,15/-"}

	if !slices.Equal(got, want) {
		t.Errorf("full-body = %q, want %q", got, want)
	}
}

func TestSyncStoresHeartRateZonesAsTheSetDifficulty(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	got := f.strings(t, `SELECT string_agg(s.effort_value || '@' || s.difficulty_value, ',' ORDER BY s."order")
		FROM template_sets s JOIN template_activities a ON a.id = s.activity_id
		JOIN workout_templates t ON t.id = a.template_id
		WHERE t.slug = 'easy-spin' GROUP BY a."order" ORDER BY a."order"`)

	if want := []string{"1800@1.0", "600@2.0,600@2.0"}; !slices.Equal(got, want) {
		t.Errorf("easy-spin sets = %q, want %q", got, want)
	}
}

func TestSyncStoresTheProgramScheduleWithARepeatedTemplate(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	got := f.strings(t, `SELECT pwt.weekday || ' ' || t.slug FROM program_workout_template pwt
		JOIN workout_templates t ON t.id = pwt.template_id ORDER BY pwt.id`)

	if want := []string{"monday full-body", "thursday full-body", "saturday easy-spin"}; !slices.Equal(got, want) {
		t.Errorf("schedule = %q", got)
	}
}

func TestASecondSyncChangesNothing(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	ids := func() []string {
		return f.strings(t, "SELECT string_agg(id::text, ',' ORDER BY id) FROM (SELECT id FROM template_activities UNION ALL SELECT id FROM template_sets) x")
	}
	before := ids()

	report := f.sync(t, nil)

	if len(report.Changes) > 0 || report.Total(catalogsync.Unchanged) != 13 {
		t.Errorf("changes %v, unchanged %d", report.Changes, report.Total(catalogsync.Unchanged))
	}

	if !slices.Equal(before, ids()) || f.count(t, "exercises") != 4 || f.count(t, "program_workout_template") != 3 {
		t.Error("the second sync rewrote rows")
	}
}

func TestSyncAdoptsAnExistingRowByItsEnglishName(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	existing := f.unmanagedExercise(t, "Bench Press")
	activity := f.logWorkoutWith(t, existing)

	report := f.sync(t, nil)

	if report.Count(ports.KindExercises, catalogsync.Adopted) != 1 || report.Count(ports.KindExercises, catalogsync.Created) != 3 {
		t.Errorf("changes: %v", report.Changes)
	}

	if f.count(t, "exercises") != 4 || f.slugID(t, "exercises", "bench-press") != existing {
		t.Error("the existing row was not adopted")
	}

	if f.id(t, "SELECT exercise_id FROM workout_activities WHERE id = $1", activity) != existing {
		t.Error("the logged activity lost its exercise")
	}
}

func TestSyncMatchesNamesRegardlessOfCaseAndSurroundingSpaces(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	existing := f.unmanagedExercise(t, " bench PRESS ")

	f.sync(t, nil)

	if got := f.strings(t, "SELECT slug || '|' || name FROM exercises WHERE id = $1", existing); !slices.Equal(got, []string{"bench-press|Bench Press"}) {
		t.Errorf("row = %q", got)
	}
}

func TestSyncAdoptsAnExistingRowByAPreviousNameAndRenamesIt(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	existing := f.unmanagedExercise(t, "Old Bench Press")
	activity := f.logWorkoutWith(t, existing)

	f.sync(t, nil)

	got := f.strings(t, "SELECT slug || '|' || name || '|' || (translations #>> '{ru,name}') FROM exercises WHERE id = $1", existing)
	if !slices.Equal(got, []string{"bench-press|Bench Press|Жим лёжа"}) {
		t.Errorf("row = %q", got)
	}

	if f.id(t, "SELECT exercise_id FROM workout_activities WHERE id = $1", activity) != existing || f.count(t, "exercises") != 4 {
		t.Error("history or row count changed")
	}
}

func TestSyncAdoptsTheOldestRowAndWarnsWhenSeveralMatch(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	first := f.unmanagedExercise(t, "Bench Press")
	second := f.unmanagedExercise(t, "Bench Press")

	report := f.sync(t, nil)

	if f.slugID(t, "exercises", "bench-press") != first {
		t.Error("the oldest row was not adopted")
	}

	if got := f.strings(t, "SELECT slug FROM exercises WHERE id = $1", second); !slices.Equal(got, []string{"<nil>"}) {
		t.Errorf("second row slug = %q", got)
	}

	if len(report.Warnings) != 1 || !strings.Contains(report.Warnings[0], "exercises [bench-press]: 2 rows match by name") {
		t.Errorf("warnings = %q", report.Warnings)
	}
}

func TestSyncPrefersAnActiveRowOverARetiredOne(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	retired := f.unmanagedExercise(t, "Bench Press")
	f.exec(t, "UPDATE exercises SET deleted_at = now() WHERE id = $1", retired)
	active := f.unmanagedExercise(t, "Bench Press")

	f.sync(t, nil)

	if f.slugID(t, "exercises", "bench-press") != active {
		t.Error("the active row was not adopted")
	}

	if got := f.strings(t, "SELECT slug FROM exercises WHERE id = $1", retired); !slices.Equal(got, []string{"<nil>"}) {
		t.Errorf("retired row slug = %q", got)
	}
}

func TestSyncOverwritesAdminEditsToManagedRecords(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	f.exec(t, `UPDATE exercises SET rest_time_seconds = 45, name = 'My Bench Press' WHERE slug = 'bench-press'`)
	f.exec(t, `DELETE FROM category_exercise WHERE exercise_id = (SELECT id FROM exercises WHERE slug = 'bench-press')`)
	f.exec(t, `INSERT INTO category_exercise (exercise_id, category_id)
		SELECT (SELECT id FROM exercises WHERE slug = 'bench-press'), id FROM categories WHERE slug = 'legs'`)
	f.exec(t, `UPDATE equipment SET difficulty_unit = 'pounds' WHERE slug = 'barbell'`)

	report := f.sync(t, nil)

	got := f.strings(t, `SELECT e.rest_time_seconds || '|' || e.name || '|' || c.slug || '|' || eq.difficulty_unit
		FROM exercises e JOIN category_exercise ce ON ce.exercise_id = e.id JOIN categories c ON c.id = ce.category_id
		JOIN equipment eq ON eq.id = e.equipment_id WHERE e.slug = 'bench-press'`)
	if !slices.Equal(got, []string{"120|Bench Press|chest|kilograms"}) {
		t.Errorf("bench-press = %q", got)
	}

	if report.Count(ports.KindExercises, catalogsync.Updated) != 1 || report.Count(ports.KindEquipment, catalogsync.Updated) != 1 {
		t.Errorf("changes = %q", report.Changes)
	}

	if !slices.Contains(report.Changes, "exercises [bench-press]: updated, changed rest_time_seconds, categories, name (en)") {
		t.Errorf("changes = %q", report.Changes)
	}
}

func TestSyncRewritesATemplateWhoseSetsWereEdited(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	firstSet := `SELECT s.id FROM template_sets s JOIN template_activities a ON a.id = s.activity_id
		JOIN workout_templates t ON t.id = a.template_id WHERE t.slug = 'easy-spin' ORDER BY a."order", s."order" LIMIT 1`
	f.exec(t, "UPDATE template_sets SET effort_value = 900 WHERE id = ("+firstSet+")")

	report := f.sync(t, nil)

	if report.Count(ports.KindWorkoutTemplates, catalogsync.Updated) != 1 || report.Count(ports.KindWorkoutTemplates, catalogsync.Unchanged) != 1 {
		t.Errorf("changes = %q", report.Changes)
	}

	if effort := f.id(t, "SELECT effort_value FROM template_sets WHERE id = ("+firstSet+")"); effort != 1800 {
		t.Errorf("effort = %d, want 1800", effort)
	}
}

func TestSyncRestoresTheProgramSchedule(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)
	f.exec(t, "DELETE FROM program_workout_template")

	report := f.sync(t, nil)

	if report.Count(ports.KindPrograms, catalogsync.Updated) != 1 || f.count(t, "program_workout_template") != 3 {
		t.Errorf("changes = %q", report.Changes)
	}
}

func TestSyncLeavesRecordsCreatedInTheAdminUIUntouched(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	category := f.id(t, "INSERT INTO categories (name) VALUES ('Grip') RETURNING id")
	exercise := f.unmanagedExercise(t, "Plate Pinch")

	f.sync(t, nil)

	got := f.strings(t, `SELECT coalesce(slug, '-') || '|' || name || '|' || updated_at::text FROM exercises WHERE id = $1
		UNION ALL SELECT coalesce(slug, '-') FROM categories WHERE id = $2`, exercise, category)
	if !slices.Equal(got, []string{"-|Plate Pinch|2026-10-01 00:00:00+00", "-"}) {
		t.Errorf("rows = %q", got)
	}

	if f.count(t, "exercises") != 5 {
		t.Error("an exercise was added or removed")
	}
}

func TestSyncRestoresAManagedExerciseRetiredInTheAdminUI(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)
	f.exec(t, "UPDATE exercises SET deleted_at = now() WHERE slug = 'squat'")

	report := f.sync(t, nil)

	if got := f.strings(t, "SELECT (deleted_at IS NULL)::text FROM exercises WHERE slug = 'squat'"); !slices.Equal(got, []string{"true"}) {
		t.Error("squat was not restored")
	}

	if !slices.Contains(report.Changes, "exercises [squat]: updated, changed restored") || f.count(t, "exercises") != 4 {
		t.Errorf("changes = %q", report.Changes)
	}
}

func TestSyncSoftDeletesARetiredExerciseAndKeepsItsHistory(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	exercise := f.slugID(t, "exercises", "bench-press")
	activity := f.logWorkoutWith(t, exercise)

	c := withoutBenchPress(t)
	retire(&c, ports.KindExercises, "bench-press", "Bench Press")

	report := f.sync(t, &c)

	if report.Count(ports.KindExercises, catalogsync.Retired) != 1 {
		t.Errorf("changes = %q", report.Changes)
	}

	if got := f.strings(t, "SELECT (deleted_at IS NOT NULL)::text || '|' || name FROM exercises WHERE id = $1", exercise); !slices.Equal(got, []string{"true|Bench Press"}) {
		t.Errorf("exercise = %q", got)
	}

	if f.id(t, "SELECT exercise_id FROM workout_activities WHERE id = $1", activity) != exercise {
		t.Error("history lost its exercise")
	}

	c = withoutBenchPress(t)
	retire(&c, ports.KindExercises, "bench-press", "Bench Press")

	if again := f.sync(t, &c); len(again.Changes) > 0 {
		t.Errorf("second sync changes = %q", again.Changes)
	}
}

func TestSyncRetiresAnUnmanagedExerciseByName(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	exercise := f.unmanagedExercise(t, "Behind the Neck Press")

	c := catalog(t)
	retire(&c, ports.KindExercises, "behind-the-neck-press", "Behind the Neck Press")
	f.sync(t, &c)

	got := f.strings(t, "SELECT slug || '|' || (deleted_at IS NOT NULL)::text FROM exercises WHERE id = $1", exercise)
	if !slices.Equal(got, []string{"behind-the-neck-press|true"}) {
		t.Errorf("exercise = %q", got)
	}
}

func TestSyncDoesNotDeleteARecordOnlyRemovedFromTheFiles(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	c := withoutBenchPress(t)
	f.sync(t, &c)

	if got := f.strings(t, "SELECT (deleted_at IS NULL)::text FROM exercises WHERE slug = 'bench-press'"); !slices.Equal(got, []string{"true"}) {
		t.Errorf("bench-press = %q", got)
	}
}

func TestSyncDeletesRetiredEquipmentThatNothingUses(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	rack := f.id(t, `INSERT INTO equipment (name, difficulty_unit, translations) VALUES ('Old Rack', 'none', '{"ru": {"name": "Старая стойка"}}') RETURNING id`)

	report := f.sync(t, nil)

	if report.Count(ports.KindEquipment, catalogsync.Retired) != 1 || f.id(t, "SELECT count(*) FROM equipment WHERE id = $1", rack) != 0 {
		t.Errorf("changes = %q", report.Changes)
	}
}

func TestSyncKeepsRetiredEquipmentThatARetiredExerciseStillUses(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	rack := f.id(t, "INSERT INTO equipment (name, difficulty_unit) VALUES ('Old Rack', 'none') RETURNING id")
	f.exec(t, "UPDATE exercises SET deleted_at = now() WHERE id = $1", f.unmanagedExercise(t, "Rack Pull", rack))

	report := f.sync(t, nil)

	if report.Count(ports.KindEquipment, catalogsync.Retired) != 0 || f.id(t, "SELECT count(*) FROM equipment WHERE id = $1", rack) != 1 {
		t.Errorf("changes = %q", report.Changes)
	}

	if want := []string{"equipment [old-rack]: not retired because exercises still use it."}; !slices.Equal(report.Warnings, want) {
		t.Errorf("warnings = %q", report.Warnings)
	}
}

func TestSyncKeepsARetiredCategoryThatExercisesBelongTo(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	category := f.id(t, "INSERT INTO categories (name) VALUES ('Grip') RETURNING id")
	f.exec(t, "INSERT INTO category_exercise (exercise_id, category_id) VALUES ($1, $2)", f.unmanagedExercise(t, "Plate Pinch"), category)

	c := catalog(t)
	retire(&c, ports.KindCategories, "grip", "Grip")
	report := f.sync(t, &c)

	if f.id(t, "SELECT count(*) FROM categories WHERE id = $1", category) != 1 ||
		!slices.Contains(report.Warnings, "categories [grip]: not retired because exercises still belong to it.") {
		t.Errorf("warnings = %q", report.Warnings)
	}
}

func TestSyncRetiresAProgramAndThenTheTemplateItReleased(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	c := catalog(t)
	c.Programs = append(without(c.Programs, "starter"), strengthOnly())
	c.WorkoutTemplates = without(c.WorkoutTemplates, "easy-spin")
	retire(&c, ports.KindPrograms, "starter", "Starter")
	retire(&c, ports.KindWorkoutTemplates, "easy-spin", "Easy Spin")

	report := f.sync(t, &c)

	if len(report.Warnings) > 0 {
		t.Errorf("warnings = %q", report.Warnings)
	}

	got := f.strings(t, "SELECT slug FROM programs UNION ALL SELECT slug FROM workout_templates ORDER BY 1")
	if !slices.Equal(got, []string{"full-body", "strength-only"}) {
		t.Errorf("programs and templates = %q", got)
	}
}

func TestSyncKeepsARetiredTemplateThatAProgramStillUses(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	program := f.id(t, "INSERT INTO programs (name) VALUES ('Mine') RETURNING id")
	f.exec(t, "INSERT INTO program_workout_template (program_id, template_id, weekday) VALUES ($1, $2, 'monday')",
		program, f.slugID(t, "workout_templates", "full-body"))

	c := catalog(t)
	c.WorkoutTemplates = without(c.WorkoutTemplates, "full-body")
	record(c, ports.KindPrograms, "starter")["schedule"] = []any{map[string]any{"weekday": "saturday", "template": "easy-spin"}}
	retire(&c, ports.KindWorkoutTemplates, "full-body", "Full Body")

	report := f.sync(t, &c)

	if f.count(t, "workout_templates") != 2 ||
		!slices.Contains(report.Warnings, "workout_templates [full-body]: not retired because a program still uses it.") {
		t.Errorf("warnings = %q", report.Warnings)
	}
}

func TestSyncKeepsARetiredProgramThatUsersAreEnrolledIn(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	program := f.slugID(t, "programs", "starter")
	user := f.id(t, "INSERT INTO users (name, email, password_hash) VALUES ('Ada', 'ada@example.com', 'x') RETURNING id")
	f.exec(t, "INSERT INTO program_user (program_id, user_id) VALUES ($1, $2)", program, user)

	c := catalog(t)
	c.Programs = append(without(c.Programs, "starter"), strengthOnly())
	retire(&c, ports.KindPrograms, "starter", "Starter")

	report := f.sync(t, &c)

	if f.id(t, "SELECT count(*) FROM program_workout_template WHERE program_id = $1", program) != 3 ||
		!slices.Contains(report.Warnings, "programs [starter]: not retired because users are enrolled in it.") {
		t.Errorf("warnings = %q", report.Warnings)
	}
}

func TestADryRunReportsChangesWithoutWriting(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	existing := f.unmanagedExercise(t, "Old Bench Press")

	report := f.sync(t, nil, catalogsync.Options{DryRun: true})

	if report.Committed || !report.DryRun ||
		report.Count(ports.KindExercises, catalogsync.Adopted) != 1 || report.Count(ports.KindExercises, catalogsync.Created) != 3 {
		t.Errorf("report = %+v", report)
	}

	if f.count(t, "exercises") != 1 || f.count(t, "programs") != 0 {
		t.Error("the dry run wrote rows")
	}

	if got := f.strings(t, "SELECT coalesce(slug, '-') || '|' || name FROM exercises WHERE id = $1", existing); !slices.Equal(got, []string{"-|Old Bench Press"}) {
		t.Errorf("existing row = %q", got)
	}
}

func TestStrictModeWritesNothingWhenTheSyncRaisesWarnings(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.unmanagedExercise(t, "Bench Press")
	f.unmanagedExercise(t, "Bench Press")

	report := f.sync(t, nil, catalogsync.Options{Strict: true})

	if report.Committed || len(report.Warnings) == 0 || f.count(t, "exercises") != 2 || f.count(t, "categories") != 0 {
		t.Errorf("committed %v, warnings %v", report.Committed, report.Warnings)
	}
}

func TestStrictModeWritesWhenThereAreNoWarnings(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)

	if report := f.sync(t, nil, catalogsync.Options{Strict: true}); !report.Committed || f.count(t, "exercises") != 4 {
		t.Errorf("committed %v", report.Committed)
	}
}

func TestAnInvalidCatalogIsRejectedBeforeAnythingIsWritten(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	c := catalog(t)
	record(c, ports.KindPrograms, "starter")["schedule"].([]any)[0].(map[string]any)["template"] = "upper-body"

	_, err := f.run(t, c, catalogsync.Options{})

	var invalid *catalogsync.InvalidError
	if !errors.As(err, &invalid) || len(invalid.Violations) != 1 ||
		!strings.Contains(invalid.Violations[0].Message, `workout template "upper-body" does not exist`) {
		t.Fatalf("err = %v", err)
	}

	if f.count(t, "equipment") != 0 || f.count(t, "exercises") != 0 {
		t.Error("rows were written")
	}
}

func TestAFailurePartWayThroughRollsEverythingBack(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.store = failingStore{f.store}

	if _, err := f.run(t, catalog(t), catalogsync.Options{}); err == nil || !strings.Contains(err.Error(), "the database went away") {
		t.Fatalf("err = %v", err)
	}

	for _, table := range []string{"equipment", "exercises", "workout_templates"} {
		if f.count(t, table) != 0 {
			t.Errorf("%s has rows", table)
		}
	}
}

func TestSyncWarnsWhenALoggedExerciseChangesItsEffortType(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)
	f.logWorkoutWith(t, f.slugID(t, "exercises", "push-up"))

	c := catalog(t)
	record(c, ports.KindExercises, "push-up")["effort_type"] = "duration"

	report := f.sync(t, &c)

	want := []string{"exercises [push-up]: effort type changes from repetitions to duration, which relabels logged workouts."}
	if !slices.Equal(report.Warnings, want) {
		t.Errorf("warnings = %q", report.Warnings)
	}

	if got := f.strings(t, "SELECT effort_type FROM exercises WHERE slug = 'push-up'"); !slices.Equal(got, []string{"duration"}) {
		t.Errorf("effort type = %q", got)
	}
}

func TestSyncWarnsWhenALoggedExerciseMovesToEquipmentWithAnotherUnit(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)
	f.logWorkoutWith(t, f.slugID(t, "exercises", "squat"))

	c := catalog(t)
	record(c, ports.KindExercises, "squat")["equipment"] = "bodyweight"
	record(c, ports.KindWorkoutTemplates, "full-body")["activities"].([]any)[1].(map[string]any)["sets"] = []any{map[string]any{"effort": 10, "difficulty": nil}}

	report := f.sync(t, &c)

	want := []string{"exercises [squat]: difficulty unit changes from kilograms to none, which relabels logged workouts."}
	if !slices.Equal(report.Warnings, want) {
		t.Errorf("warnings = %q", report.Warnings)
	}
}

func TestSyncWarnsWhenEquipmentWithLoggedExercisesChangesItsUnit(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)
	f.logWorkoutWith(t, f.slugID(t, "exercises", "squat"))

	c := catalog(t)
	record(c, ports.KindEquipment, "barbell")["difficulty_unit"] = "pounds"

	report := f.sync(t, &c)

	want := []string{"equipment [barbell]: difficulty unit changes from kilograms to pounds, which relabels logged workouts."}
	if !slices.Equal(report.Warnings, want) {
		t.Errorf("warnings = %q", report.Warnings)
	}
}

func TestSyncDoesNotWarnAboutMeasurementChangesWithoutLoggedWorkouts(t *testing.T) {
	t.Parallel()

	f := newSyncFixture(t)
	f.sync(t, nil)

	c := catalog(t)
	record(c, ports.KindEquipment, "barbell")["difficulty_unit"] = "pounds"
	record(c, ports.KindExercises, "push-up")["effort_type"] = "duration"

	if report := f.sync(t, &c); len(report.Warnings) > 0 {
		t.Errorf("warnings = %q", report.Warnings)
	}
}

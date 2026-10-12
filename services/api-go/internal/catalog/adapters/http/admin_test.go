package http_test

import (
	"encoding/json"
	"fmt"
	"net/http"
	"slices"
	"strings"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// The Go counterparts of tests/Feature/Admin/{Equipment,Category,Exercise,
// WorkoutTemplate,Program}AdminTest.php, on the catalog in
// testdata/catalog.sql: equipment 1 Barbell, 2 Bodyweight, 3 Dumbbell;
// categories 1 Chest, 2 Legs, 3 Back; exercises 1–7 (4 Upright Row
// retired, on Dumbbell); templates 1 Full Body A (uses exercise 4), 2 Core;
// programs 1 Strength (assigns both templates), 2 Cardio.

func (f *fixture) admin(t *testing.T) user {
	t.Helper()

	u := f.signUp(t, "admin@example.com")
	f.exec(t, "INSERT INTO role_user (user_id, role_id) SELECT $1, id FROM roles WHERE name = 'Admin'", u.ID)

	return u
}

func (f *fixture) call(u user, method, path string, body any, locale ...string) *apitest.Response {
	req := apitest.Request{Method: method, Path: "/api/v1/admin/" + path, Token: u.Token, Body: body}
	if len(locale) > 0 {
		req.Locale = locale[0]
	}

	return f.api.Do(req)
}

type adminBody struct {
	Data json.RawMessage `json:"data"`
}

func data(t *testing.T, res *apitest.Response, v any) {
	t.Helper()

	var body adminBody

	decode(t, res, &body)

	if err := json.Unmarshal(body.Data, v); err != nil {
		t.Fatalf("decode data %s: %v", body.Data, err)
	}
}

// pointers asserts a 422 with exactly these pointers and returns the
// errors' codes and details by pointer.
func pointers(t *testing.T, res *apitest.Response, want ...string) map[string][2]string {
	t.Helper()

	res.AssertStatus(http.StatusUnprocessableEntity)

	got := map[string][2]string{}
	for _, e := range res.Problem().Errors {
		got[e.Pointer] = [2]string{string(e.Code), e.Detail}
	}

	var have []string
	for p := range got {
		have = append(have, p)
	}

	slices.Sort(have)
	slices.Sort(want)

	if !slices.Equal(have, want) {
		t.Errorf("errors at %v, want %v", have, want)
	}

	return got
}

func nameTranslations(en, ru any) map[string]any {
	t := map[string]any{"en": map[string]any{"name": en}}
	if ru != nil {
		t["ru"] = map[string]any{"name": ru}
	}

	return t
}

// adminOperations are the 25 admin operations, against existing ids.
var adminOperations = []struct{ method, path string }{
	{http.MethodGet, "equipment"},
	{http.MethodPost, "equipment"},
	{http.MethodGet, "equipment/1"},
	{http.MethodPut, "equipment/1"},
	{http.MethodDelete, "equipment/1"},
	{http.MethodGet, "categories"},
	{http.MethodPost, "categories"},
	{http.MethodGet, "categories/1"},
	{http.MethodPut, "categories/1"},
	{http.MethodDelete, "categories/1"},
	{http.MethodGet, "exercises"},
	{http.MethodPost, "exercises"},
	{http.MethodGet, "exercises/1"},
	{http.MethodPut, "exercises/1"},
	{http.MethodDelete, "exercises/1"},
	{http.MethodGet, "workout-templates"},
	{http.MethodPost, "workout-templates"},
	{http.MethodGet, "workout-templates/1"},
	{http.MethodPut, "workout-templates/1"},
	{http.MethodDelete, "workout-templates/1"},
	{http.MethodGet, "programs"},
	{http.MethodPost, "programs"},
	{http.MethodGet, "programs/1"},
	{http.MethodPut, "programs/1"},
	{http.MethodDelete, "programs/1"},
}

// The non_admin_cannot_* and guest_cannot_* tests: a verified user who is
// not an admin gets 403 forbidden on every admin operation and changes
// nothing; a guest gets 401.
func TestAdminOperationsNeedAnAdmin(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	for _, op := range adminOperations {
		res := f.api.Do(apitest.Request{Method: op.method, Path: "/api/v1/admin/" + op.path, Token: u.Token, Body: map[string]any{}, SkipContract: true})
		if res.Code != http.StatusForbidden || res.Problem().Code != "forbidden" {
			t.Errorf("%s %s as a user: %d %s", op.method, op.path, res.Code, res.Body)
		}

		res = f.api.Do(apitest.Request{Method: op.method, Path: "/api/v1/admin/" + op.path, Body: map[string]any{}, SkipContract: true})
		if res.Code != http.StatusUnauthorized {
			t.Errorf("%s %s as a guest: %d", op.method, op.path, res.Code)
		}
	}

	if f.count(t, "SELECT count(*) FROM equipment")+f.count(t, "SELECT count(*) FROM programs") != 5 {
		t.Error("a forbidden call changed the catalog")
	}
}

// Every admin list, in English and Russian, against golden files: id
// order, counts (active exercises only; a template assigned twice counts
// twice), translation maps with null for a missing locale, and no nested
// rows (admin_can_list_*).
func TestAdminLists(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	for _, res := range []string{"equipment", "categories", "exercises", "workout-templates", "programs"} {
		f.call(a, http.MethodGet, res, nil, "ru").AssertStatus(http.StatusOK).AssertGolden("admin-" + res + ".ru.golden.json")
	}
}

// The single-record views (admin_can_show_*), with their nested rows and no
// counts; a retired exercise is not found (a_retired_exercise_is_excluded_from_the_admin_list).
func TestAdminShow(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	for _, path := range []string{"equipment/1", "categories/1", "exercises/6", "workout-templates/1", "programs/1"} {
		name := strings.ReplaceAll(path, "/", "-")
		f.call(a, http.MethodGet, path, nil).AssertStatus(http.StatusOK).AssertGolden("admin-" + name + ".golden.json")
	}

	for _, path := range []string{"equipment/99", "categories/99", "exercises/4", "exercises/99", "workout-templates/99", "programs/99"} {
		res := f.call(a, http.MethodGet, path, nil)
		if res.Code != http.StatusNotFound {
			t.Errorf("GET %s = %d, want 404", path, res.Code)
		}
	}
}

// Mirrors EquipmentAdminTest::admin_can_create_equipment_with_translations,
// ::admin_can_update_equipment_and_translations and
// ::update_removing_russian_falls_back_to_english: values are trimmed, a
// blank Russian name removes the translation and Russian reads fall back.
func TestAdminEquipmentWrites(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	res := f.call(a, http.MethodPost, "equipment", map[string]any{"difficulty_unit": "kilograms", "translations": nameTranslations("  Sandbag ", "Мешок")}).
		AssertStatus(http.StatusCreated)
	res.AssertGolden("admin-equipment-created.golden.json")

	var created struct{ ID int64 }

	data(t, res, &created)

	f.call(a, http.MethodPut, fmt.Sprintf("equipment/%d", created.ID), map[string]any{"difficulty_unit": "pounds", "translations": nameTranslations("Sand Bag", "")}).
		AssertStatus(http.StatusOK)

	var got struct {
		Name           string `json:"name"`
		DifficultyUnit string `json:"difficulty_unit"`
		Translations   struct {
			Name map[string]*string `json:"name"`
		} `json:"translations"`
	}

	data(t, f.call(a, http.MethodGet, fmt.Sprintf("equipment/%d", created.ID), nil, "ru").AssertStatus(http.StatusOK), &got)

	if got.Name != "Sand Bag" || got.DifficultyUnit != "pounds" || *got.Translations.Name["en"] != "Sand Bag" || got.Translations.Name["ru"] != nil {
		t.Errorf("updated equipment = %+v", got)
	}
}

// Mirrors the create_requires_english_name tests and
// EquipmentAdminTest::create_rejects_invalid_difficulty_unit and
// CategoryAdminTest::create_rejects_a_locale_block_that_is_a_list_instead_of_an_object.
func TestAdminValidatesTranslations(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	for _, tt := range []struct {
		path string
		body map[string]any
	}{
		{"equipment", map[string]any{"difficulty_unit": "kilograms", "translations": nameTranslations("", "Штанга")}},
		{"equipment", map[string]any{"difficulty_unit": "kilograms", "translations": nameTranslations("   ", nil)}},
		{"categories", map[string]any{"translations": map[string]any{"en": map[string]any{}}}},
	} {
		errs := pointers(t, f.call(a, http.MethodPost, tt.path, tt.body), "/translations/en/name")
		if errs["/translations/en/name"] != [2]string{"required", "An English name is required."} {
			t.Errorf("%s %v: %v", tt.path, tt.body, errs)
		}
	}

	pointers(t, f.call(a, http.MethodPost, "equipment", map[string]any{"difficulty_unit": "stones", "translations": nameTranslations("Rack", nil)}), "/difficulty_unit")
	pointers(t, f.call(a, http.MethodPost, "categories", map[string]any{"translations": map[string]any{"en": map[string]any{"name": "Chest"}, "ru": []any{map[string]any{"name": "Грудь"}}}}), "/translations/ru")
	pointers(t, f.call(a, http.MethodPost, "categories", map[string]any{"translations": []any{map[string]any{"name": "Chest"}}}), "/translations")

	errs := pointers(t, f.call(a, http.MethodPost, "categories", map[string]any{"translations": nameTranslations(strings.Repeat("x", 256), nil)}), "/translations/en/name")
	if errs["/translations/en/name"][1] != "The translations.en.name field must not be greater than 255 characters." {
		t.Errorf("too long: %v", errs)
	}

	if f.count(t, "SELECT count(*) FROM equipment") != 3 || f.count(t, "SELECT count(*) FROM categories") != 3 {
		t.Error("a rejected create wrote a row")
	}
}

// Mirrors EquipmentAdminTest::admin_can_delete_unused_equipment,
// ::cannot_delete_equipment_in_use_by_exercises and
// ::cannot_delete_equipment_referenced_only_by_a_retired_exercise.
func TestAdminDeleteEquipmentGuard(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	for _, id := range []int{1, 3} { // 3 is used only by the retired Upright Row
		res := f.call(a, http.MethodDelete, fmt.Sprintf("equipment/%d", id), nil)
		res.AssertStatus(http.StatusConflict)

		if p := res.Problem(); p.Code != "in_use" || p.Detail != "Cannot delete equipment that is used by exercises." {
			t.Errorf("equipment %d: %+v", id, p)
		}
	}

	var created struct{ ID int64 }

	data(t, f.call(a, http.MethodPost, "equipment", map[string]any{"difficulty_unit": "none", "translations": nameTranslations("Mat", nil)}).AssertStatus(http.StatusCreated), &created)
	f.call(a, http.MethodDelete, fmt.Sprintf("equipment/%d", created.ID), nil).AssertStatus(http.StatusNoContent)

	if f.count(t, "SELECT count(*) FROM equipment") != 3 {
		t.Error("equipment rows changed")
	}
}

// Mirrors CategoryAdminTest::admin_can_create_category, ::admin_can_update_category
// and ::admin_can_delete_category: deleting a category takes its exercises
// out of it.
func TestAdminCategoryWrites(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	f.call(a, http.MethodPost, "categories", map[string]any{"translations": nameTranslations("Grip", "Хват")}).AssertStatus(http.StatusCreated)
	f.call(a, http.MethodPut, "categories/2", map[string]any{"translations": nameTranslations("Lower Body", "")}).AssertStatus(http.StatusOK)

	var legs struct {
		Name string `json:"name"`
	}

	data(t, f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/admin/categories/2", Token: a.Token, Locale: "ru"}), &legs)

	if legs.Name != "Lower Body" {
		t.Errorf("category 2 in Russian = %q, want the English fallback", legs.Name)
	}

	f.call(a, http.MethodDelete, "categories/2", nil).AssertStatus(http.StatusNoContent)

	if f.count(t, "SELECT count(*) FROM category_exercise WHERE category_id = 2") != 0 || f.count(t, "SELECT count(*) FROM exercises") != 7 {
		t.Error("the category's links or exercises are wrong after delete")
	}
}

// Mirrors ExerciseAdminTest::admin_can_create_exercise_with_categories,
// ::admin_can_create_exercise_with_blank_russian_description,
// ::admin_can_create_exercise_without_rest_time,
// ::admin_can_update_exercise_and_resync_categories,
// ::create_requires_valid_equipment and ::create_rejects_invalid_effort_type.
func TestAdminExerciseWrites(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	body := map[string]any{
		"equipment_id": 1, "effort_type": "repetitions", "rest_time_seconds": 0, "category_ids": []int{3, 1},
		"translations": map[string]any{
			"en": map[string]any{"name": "Floor Press", "description": "Press from the floor."},
			"ru": map[string]any{"name": "Жим с пола", "description": nil},
		},
	}

	res := f.call(a, http.MethodPost, "exercises", body).AssertStatus(http.StatusCreated)
	res.AssertGolden("admin-exercise-created.golden.json")

	var created struct{ ID int64 }

	data(t, res, &created)

	if f.count(t, "SELECT count(*) FROM exercises WHERE id = $1 AND rest_time_seconds = 0 AND NOT translations->'ru' ? 'description'", created.ID) != 1 {
		t.Error("the stored exercise is wrong")
	}

	body["rest_time_seconds"], body["category_ids"] = nil, []int{2}
	f.call(a, http.MethodPut, fmt.Sprintf("exercises/%d", created.ID), body).AssertStatus(http.StatusOK)

	if f.count(t, "SELECT count(*) FROM exercises WHERE id = $1 AND rest_time_seconds IS NULL", created.ID) != 1 ||
		f.count(t, "SELECT count(*) FROM category_exercise WHERE exercise_id = $1 AND category_id = 2", created.ID) != 1 ||
		f.count(t, "SELECT count(*) FROM category_exercise WHERE exercise_id = $1", created.ID) != 1 {
		t.Error("the update did not replace the rest time or categories")
	}

	errs := pointers(t, f.call(a, http.MethodPost, "exercises", map[string]any{
		"equipment_id": 99, "effort_type": "repetitions", "category_ids": []int{1, 99}, "translations": nameTranslations("Nope", nil),
	}), "/equipment_id", "/category_ids/1")
	if errs["/category_ids/1"] != [2]string{"not_found", "The selected category_ids.1 is invalid."} {
		t.Errorf("errors = %v", errs)
	}

	pointers(t, f.call(a, http.MethodPost, "exercises", map[string]any{"equipment_id": 1, "effort_type": "reps", "translations": nameTranslations("Nope", nil)}), "/effort_type")
}

// Mirrors ExerciseAdminTest::admin_can_delete_exercise_which_soft_deletes_it,
// ::a_retired_exercise_is_excluded_from_the_admin_list and
// ::retiring_an_in_use_exercise_preserves_workout_history_and_name.
func TestAdminDeleteExerciseRetiresIt(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	f.call(a, http.MethodDelete, "exercises/1", nil).AssertStatus(http.StatusNoContent)
	f.call(a, http.MethodDelete, "exercises/1", nil).AssertStatus(http.StatusNotFound)

	if f.count(t, "SELECT count(*) FROM exercises WHERE id = 1 AND deleted_at IS NOT NULL AND translations->'ru'->>'name' = 'Приседания'") != 1 {
		t.Error("the exercise was not retired with its translations")
	}

	var list []struct{ ID int64 }

	data(t, f.call(a, http.MethodGet, "exercises", nil), &list)

	for _, e := range list {
		if e.ID == 1 {
			t.Error("the retired exercise is in the admin list")
		}
	}

	var template struct {
		Data struct {
			Activities []struct {
				Exercise struct {
					Name string `json:"name"`
				} `json:"exercise"`
			} `json:"activities"`
		} `json:"data"`
	}

	decode(t, f.get(a, "/api/v1/workout-templates/1", "en"), &template)

	if template.Data.Activities[0].Exercise.Name != "Squat" {
		t.Errorf("the template's first activity shows %q", template.Data.Activities[0].Exercise.Name)
	}
}

func templateBody(name string, activities ...map[string]any) map[string]any {
	return map[string]any{"translations": map[string]any{"en": map[string]any{"name": name}}, "activities": activities}
}

func templateActivity(exercise, order int, sets ...map[string]any) map[string]any {
	return map[string]any{"exercise_id": exercise, "order": order, "sets": sets}
}

func templateSet(order, effort int, difficulty any) map[string]any {
	return map[string]any{"order": order, "effort_value": effort, "difficulty_value": difficulty}
}

// Mirrors WorkoutTemplateAdminTest::admin_can_create_template_with_activities_and_sets,
// ::admin_can_update_template_replacing_activities and
// ::admin_can_delete_template_and_its_activities_and_sets.
func TestAdminTemplateWrites(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	res := f.call(a, http.MethodPost, "workout-templates", templateBody("Push Day",
		templateActivity(2, 1, templateSet(1, 8, nil), templateSet(2, 6, nil)),
		templateActivity(3, 2, templateSet(1, 15, nil)),
	)).AssertStatus(http.StatusCreated)
	res.AssertGolden("admin-template-created.golden.json")

	var created struct{ ID int64 }

	data(t, res, &created)

	f.call(a, http.MethodPut, fmt.Sprintf("workout-templates/%d", created.ID), templateBody("Push Day",
		templateActivity(6, 1, templateSet(1, 5, nil)),
	)).AssertStatus(http.StatusOK)

	if f.count(t, "SELECT count(*) FROM template_activities WHERE template_id = $1 AND exercise_id = 6", created.ID) != 1 ||
		f.count(t, "SELECT count(*) FROM template_activities WHERE template_id = $1", created.ID) != 1 ||
		f.count(t, "SELECT count(*) FROM template_sets s JOIN template_activities a ON a.id = s.activity_id WHERE a.template_id = $1", created.ID) != 1 {
		t.Error("the update did not replace the activities")
	}

	f.call(a, http.MethodDelete, fmt.Sprintf("workout-templates/%d", created.ID), nil).AssertStatus(http.StatusNoContent)

	if f.count(t, "SELECT count(*) FROM template_activities WHERE template_id = $1", created.ID) != 0 {
		t.Error("the deleted template's activities remain")
	}
}

// Mirrors WorkoutTemplateAdminTest::create_requires_at_least_one_activity,
// ::create_requires_each_activity_to_have_sets, ::create_rejects_out_of_range_heart_rate_zone
// and ::create_rejects_numeric_values_beyond_column_range, plus the Go
// checks: repeated orders, and retired exercises (ADR 0012). A failing
// update changes nothing.
func TestAdminTemplateValidation(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	pointers(t, f.call(a, http.MethodPost, "workout-templates", templateBody("Empty")), "/activities")
	pointers(t, f.call(a, http.MethodPost, "workout-templates", templateBody("No sets", templateActivity(2, 1))), "/activities/0/sets")
	pointers(t, f.call(a, http.MethodPost, "workout-templates", templateBody("Big",
		templateActivity(2, 1001, templateSet(1, 100001, 123456.5)))),
		"/activities/0/order", "/activities/0/sets/0/effort_value", "/activities/0/sets/0/difficulty_value")
	pointers(t, f.call(a, http.MethodPost, "workout-templates", templateBody("Repeats",
		templateActivity(2, 1, templateSet(1, 5, nil), templateSet(1, 5, nil)), templateActivity(3, 1, templateSet(1, 5, nil)))),
		"/activities/0/order", "/activities/1/order", "/activities/0/sets/0/order", "/activities/0/sets/1/order")
	pointers(t, f.call(a, http.MethodPost, "workout-templates", templateBody("Retired", templateActivity(4, 1, templateSet(1, 5, nil)))),
		"/activities/0/exercise_id")

	// Template 1 already uses the retired Upright Row: it may keep it.
	f.call(a, http.MethodPut, "workout-templates/1", templateBody("Full Body A", templateActivity(4, 1, templateSet(1, 10, nil)))).
		AssertStatus(http.StatusOK)

	before := f.count(t, "SELECT count(*) FROM template_sets")
	pointers(t, f.call(a, http.MethodPut, "workout-templates/2", templateBody("Core", templateActivity(5, 1, templateSet(1, 60, nil)), templateActivity(99, 2, templateSet(1, 5, nil)))),
		"/activities/1/exercise_id")

	if f.count(t, "SELECT count(*) FROM template_sets") != before {
		t.Error("a rejected update wrote sets")
	}
}

// Heart-rate zones need an exercise on zone equipment; the catalog has
// none, so one is added.
func TestAdminTemplateHeartRateZone(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)
	f.exec(t, "INSERT INTO equipment (id, name, difficulty_unit) OVERRIDING SYSTEM VALUE VALUES (9, 'Bike', 'heart_rate_zone')")
	f.exec(t, "INSERT INTO exercises (id, name, equipment_id, effort_type) OVERRIDING SYSTEM VALUE VALUES (9, 'Ride', 9, 'duration')")

	errs := pointers(t, f.call(a, http.MethodPost, "workout-templates", templateBody("Ride", templateActivity(9, 1, templateSet(1, 600, 6)))),
		"/activities/0/sets/0/difficulty_value")
	if errs["/activities/0/sets/0/difficulty_value"] != [2]string{"out_of_range", "Heart-rate zone must be between 1 and 5."} {
		t.Errorf("errors = %v", errs)
	}

	f.call(a, http.MethodPost, "workout-templates", templateBody("Ride", templateActivity(9, 1, templateSet(1, 600, 2), templateSet(2, 300, nil)))).
		AssertStatus(http.StatusCreated)
}

// Mirrors WorkoutTemplateAdminTest::cannot_delete_a_template_that_is_used_by_a_program.
func TestAdminDeleteTemplateGuard(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	res := f.call(a, http.MethodDelete, "workout-templates/1", nil)
	res.AssertStatus(http.StatusConflict)

	if p := res.Problem(); p.Code != "in_use" || p.Detail != "Cannot delete a workout template that is used by a program." {
		t.Errorf("problem = %+v", p)
	}

	if f.count(t, "SELECT count(*) FROM template_activities WHERE template_id = 1") != 3 {
		t.Error("the guarded template lost activities")
	}
}

// Mirrors ProgramAdminTest::admin_can_create_program_with_weekday_assignments,
// ::admin_can_assign_the_same_template_to_multiple_weekdays,
// ::program_can_be_created_without_assignments,
// ::admin_can_update_program_replacing_assignments and
// ::admin_can_delete_program_and_its_assignments.
func TestAdminProgramWrites(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	body := map[string]any{
		"translations": map[string]any{"en": map[string]any{"name": "Twice", "description": "Monday and Thursday."}},
		"assignments": []any{
			map[string]any{"workout_template_id": 2, "weekday": "monday"},
			map[string]any{"workout_template_id": 2, "weekday": "thursday"},
		},
	}

	res := f.call(a, http.MethodPost, "programs", body).AssertStatus(http.StatusCreated)
	res.AssertGolden("admin-program-created.golden.json")

	var created struct{ ID int64 }

	data(t, res, &created)

	body["assignments"] = []any{map[string]any{"workout_template_id": 1, "weekday": "friday"}}

	var updated struct {
		Assignments []struct {
			WorkoutTemplateID int64  `json:"workout_template_id"`
			Weekday           string `json:"weekday"`
		} `json:"assignments"`
	}

	data(t, f.call(a, http.MethodPut, fmt.Sprintf("programs/%d", created.ID), body).AssertStatus(http.StatusOK), &updated)

	if len(updated.Assignments) != 1 || updated.Assignments[0].WorkoutTemplateID != 1 || updated.Assignments[0].Weekday != "friday" {
		t.Errorf("assignments = %+v", updated.Assignments)
	}

	f.call(a, http.MethodPost, "programs", map[string]any{"translations": nameTranslations("Empty", nil), "assignments": []any{}}).AssertStatus(http.StatusCreated)

	f.exec(t, "INSERT INTO program_user (program_id, user_id) VALUES ($1, $2)", created.ID, a.ID)
	f.call(a, http.MethodDelete, fmt.Sprintf("programs/%d", created.ID), nil).AssertStatus(http.StatusNoContent)

	if f.count(t, "SELECT count(*) FROM program_workout_template WHERE program_id = $1", created.ID)+
		f.count(t, "SELECT count(*) FROM program_user WHERE program_id = $1", created.ID) != 0 {
		t.Error("the deleted program's assignments or enrollments remain")
	}
}

// Mirrors ProgramAdminTest::create_rejects_invalid_weekday and
// ::create_rejects_unknown_template.
func TestAdminProgramValidation(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	a := f.admin(t)

	pointers(t, f.call(a, http.MethodPost, "programs", map[string]any{
		"translations": nameTranslations("P", nil), "assignments": []any{map[string]any{"workout_template_id": 1, "weekday": "funday"}},
	}), "/assignments/0/weekday")

	errs := pointers(t, f.call(a, http.MethodPost, "programs", map[string]any{
		"translations": nameTranslations("P", nil), "assignments": []any{map[string]any{"workout_template_id": 99, "weekday": "monday"}},
	}), "/assignments/0/workout_template_id")
	if errs["/assignments/0/workout_template_id"] != [2]string{"not_found", "The selected assignments.0.workout_template_id is invalid."} {
		t.Errorf("errors = %v", errs)
	}
}

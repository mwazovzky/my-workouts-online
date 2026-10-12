package http_test

import (
	"net/http"
	"slices"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// The PHP API's responses for the same catalog (testdata/README.md); among
// them the Go counterparts of ProgramApiTest::show_returns_program_with_workout_templates,
// WorkoutTemplateApiTest::show_returns_template_with_activities_and_sets,
// ExerciseApiTest::index_returns_exercises_with_equipment_and_categories and
// ::index_omits_the_per_locale_translations, and
// TranslatedResourceTest::program_show_returns_russian_content_for_russian_user.
func TestCatalogResponsesMatchThePHPAPI(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")
	f.enrollment(u, http.MethodPut, "1").AssertStatus(http.StatusNoContent)

	paths := map[string]string{
		"programs":           "/api/v1/programs",
		"program-1":          "/api/v1/programs/1",
		"program-2":          "/api/v1/programs/2",
		"workout-template-1": "/api/v1/workout-templates/1",
		"workout-template-2": "/api/v1/workout-templates/2",
		"exercises":          "/api/v1/exercises",
	}

	for golden, path := range paths {
		for _, locale := range []string{"en", "ru"} {
			t.Run(golden+"."+locale, func(t *testing.T) {
				t.Parallel()

				f.get(u, path, locale).AssertStatus(http.StatusOK).AssertGolden(golden + "." + locale + ".golden.json")
			})
		}
	}
}

// ProgramApiTest::index_returns_all_programs_with_enrollment_status.
func TestListProgramsFlagsTheCallersEnrollment(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	ada, bob := f.signUp(t, "ada@example.com"), f.signUp(t, "bob@example.com")
	f.enrollment(bob, http.MethodPut, "2").AssertStatus(http.StatusNoContent)

	for _, tt := range []struct {
		user user
		want []bool
	}{{ada, []bool{false, false}}, {bob, []bool{false, true}}} {
		var body struct {
			Data []struct {
				IsEnrolled bool `json:"is_enrolled"`
			} `json:"data"`
		}

		decode(t, f.get(tt.user, "/api/v1/programs", "").AssertStatus(http.StatusOK), &body)

		got := make([]bool, len(body.Data))
		for i, p := range body.Data {
			got[i] = p.IsEnrolled
		}

		if !slices.Equal(got, tt.want) {
			t.Errorf("user %d: is_enrolled = %v, want %v", tt.user.ID, got, tt.want)
		}
	}
}

// ExerciseApiTest::index_excludes_retired_exercises and
// ::index_sorts_exercises_by_name: Upright Row is retired; "bench press" sorts
// first despite its lowercase; under ru the Latin "Plank" (no translation)
// comes before the Cyrillic names.
func TestListExercisesHidesRetiredAndSortsByLocalizedName(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	tests := map[string][]string{
		"en": {"bench press", "Burpee", "Deadlift", "Plank", "Push-up", "Squat"},
		"ru": {"Plank", "Бёрпи", "Жим лёжа", "Отжимания", "Приседания", "Становая тяга"},
	}

	for locale, want := range tests {
		if got := names(t, f.get(u, "/api/v1/exercises", locale).AssertStatus(http.StatusOK)); !slices.Equal(got, want) {
			t.Errorf("%s: %q, want %q", locale, got, want)
		}
	}
}

// ProgramApiTest::enroll_adds_user_to_program and ::enroll_is_idempotent,
// ProgramEnrollTest::authenticated_user_can_enroll_in_a_program,
// ::user_cannot_enroll_in_a_program_twice and
// ::enrolling_twice_leaves_one_enrollment.
func TestEnrollIsIdempotent(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	f.enrollment(u, http.MethodPut, "1").AssertStatus(http.StatusNoContent)
	f.enrollment(u, http.MethodPut, "1").AssertStatus(http.StatusNoContent)

	if n := f.count(t, "SELECT count(*) FROM program_user WHERE program_id = 1 AND user_id = $1", u.ID); n != 1 {
		t.Errorf("enrollments = %d, want 1", n)
	}
}

// ProgramEnrollTest::unenroll_removes_the_enrollment_and_is_idempotent.
func TestUnenrollIsIdempotent(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")
	f.enrollment(u, http.MethodPut, "1").AssertStatus(http.StatusNoContent)

	f.enrollment(u, http.MethodDelete, "1").AssertStatus(http.StatusNoContent)
	f.enrollment(u, http.MethodDelete, "1").AssertStatus(http.StatusNoContent)

	if n := f.count(t, "SELECT count(*) FROM program_user"); n != 0 {
		t.Errorf("enrollments = %d, want 0", n)
	}

	var body struct {
		Data []struct {
			IsEnrolled bool `json:"is_enrolled"`
		} `json:"data"`
	}

	decode(t, f.get(u, "/api/v1/programs", "").AssertStatus(http.StatusOK), &body)

	if body.Data[0].IsEnrolled {
		t.Error("still enrolled after unenrolling")
	}
}

// ProgramEnrollTest::unenroll_only_removes_the_callers_enrollment_and_keeps_their_workouts
// (the dashboard half moves to story 9).
func TestUnenrollKeepsOtherEnrollmentsAndTheCallersWorkouts(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	ada, bob := f.signUp(t, "ada@example.com"), f.signUp(t, "bob@example.com")
	f.enrollment(ada, http.MethodPut, "1").AssertStatus(http.StatusNoContent)
	f.enrollment(bob, http.MethodPut, "1").AssertStatus(http.StatusNoContent)
	f.exec(t, "INSERT INTO workouts (user_id, template_id, name) VALUES ($1, 1, 'Full Body A')", ada.ID)

	f.enrollment(ada, http.MethodDelete, "1").AssertStatus(http.StatusNoContent)

	if n := f.count(t, "SELECT count(*) FROM program_user WHERE user_id = $1", bob.ID); n != 1 {
		t.Errorf("bob's enrollments = %d, want 1", n)
	}

	if n := f.count(t, "SELECT count(*) FROM workouts WHERE user_id = $1", ada.ID); n != 1 {
		t.Errorf("ada's workouts = %d, want 1", n)
	}
}

// ProgramEnrollTest::the_database_refuses_a_duplicate_enrollment.
func TestTheDatabaseRefusesADuplicateEnrollment(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")
	f.enrollment(u, http.MethodPut, "1").AssertStatus(http.StatusNoContent)

	if _, err := f.pool.Exec(t.Context(), "INSERT INTO program_user (program_id, user_id) VALUES (1, $1)", u.ID); err == nil {
		t.Error("a second enrollment row was accepted")
	}
}

// ProgramApiTest::show_returns_404_for_nonexistent_program,
// ProgramEnrollTest::cannot_enroll_in_a_nonexistent_program,
// ::unenroll_from_a_nonexistent_program_is_not_found and
// ::the_old_enroll_action_is_gone, WorkoutTemplateApiTest::show_returns_404_for_nonexistent_template
// and ::show_returns_404_for_id_larger_than_integer_range.
func TestUnknownCatalogRecordsAreNotFound(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	tests := []struct{ method, path string }{
		{http.MethodGet, "/api/v1/programs/99999"},
		{http.MethodPut, "/api/v1/programs/999/enrollment"},
		{http.MethodDelete, "/api/v1/programs/999/enrollment"},
		{http.MethodPost, "/api/v1/programs/1/enroll"},
		{http.MethodGet, "/api/v1/workout-templates/99999"},
		{http.MethodGet, "/api/v1/workout-templates/null,null"},
		{http.MethodGet, "/api/v1/workout-templates/68119702247477897854976"},
	}

	for _, tt := range tests {
		t.Run(tt.method+" "+tt.path, func(t *testing.T) {
			t.Parallel()

			res := f.api.Do(apitest.Request{Method: tt.method, Path: tt.path, Token: u.Token, SkipContract: true})
			res.AssertStatus(http.StatusNotFound)

			if code := res.Problem().Code; code != "not_found" {
				t.Errorf("code = %q, want not_found", code)
			}
		})
	}

	if n := f.count(t, "SELECT count(*) FROM program_user"); n != 0 {
		t.Errorf("enrollments = %d, want 0", n)
	}
}

// catalogOperations are the six operations of this module.
var catalogOperations = []struct{ method, path string }{
	{http.MethodGet, "/api/v1/programs"},
	{http.MethodGet, "/api/v1/programs/1"},
	{http.MethodPut, "/api/v1/programs/1/enrollment"},
	{http.MethodDelete, "/api/v1/programs/1/enrollment"},
	{http.MethodGet, "/api/v1/workout-templates/1"},
	{http.MethodGet, "/api/v1/exercises"},
}

// The *_requires_authentication tests of ProgramApiTest, WorkoutTemplateApiTest
// and ExerciseApiTest, and ProgramEnrollTest::unauthenticated_user_cannot_enroll_in_a_program.
func TestCatalogOperationsNeedASignedInUser(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	for _, op := range catalogOperations {
		res := f.api.Do(apitest.Request{Method: op.method, Path: op.path})
		res.AssertStatus(http.StatusUnauthorized)
	}

	if n := f.count(t, "SELECT count(*) FROM program_user"); n != 0 {
		t.Errorf("enrollments = %d, want 0", n)
	}
}

// The verified route group: an unverified user gets 403 email_unverified.
// The contract documents that 403 on getDashboard but not yet on these
// operations, so the response is not checked against it.
func TestCatalogOperationsNeedAVerifiedEmail(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com", true)

	for _, op := range catalogOperations {
		res := f.api.Do(apitest.Request{Method: op.method, Path: op.path, Token: u.Token, SkipContract: true})
		res.AssertStatus(http.StatusForbidden)

		if code := res.Problem().Code; code != "email_unverified" {
			t.Errorf("%s %s: code = %q, want email_unverified", op.method, op.path, code)
		}
	}
}

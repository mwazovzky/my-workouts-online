package http_test

import (
	"fmt"
	"net/http"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
)

// Mirrors WorkoutApiTest::index_returns_paginated_workouts_for_authenticated_user.
func TestListWorkoutsShowsOnlyTheCallersWorkouts(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	ada, bob := f.signUp(t, "ada@example.com"), f.signUp(t, "bob@example.com")
	f.workout(t, ada.ID, "in_progress")
	f.workout(t, ada.ID, "completed")
	f.workout(t, bob.ID, "in_progress")

	var body struct {
		Data []struct {
			UserID int64 `json:"user_id"`
		} `json:"data"`
	}

	decode(t, f.do(ada, http.MethodGet, "/api/v1/workouts", nil).AssertStatus(http.StatusOK), &body)

	if len(body.Data) != 2 || body.Data[0].UserID != ada.ID || body.Data[1].UserID != ada.ID {
		t.Errorf("workouts = %+v, want ada's two", body.Data)
	}
}

type page struct {
	Data []struct {
		ID              int64 `json:"id"`
		ActivitiesCount *int  `json:"activities_count"`
		WorkoutTemplate *struct {
			Name string `json:"name"`
		} `json:"workout_template"`
	} `json:"data"`
	Links map[string]*string `json:"links"`
	Meta  struct {
		CurrentPage int  `json:"current_page"`
		From        *int `json:"from"`
		LastPage    int  `json:"last_page"`
		PerPage     int  `json:"per_page"`
		To          *int `json:"to"`
		Total       int  `json:"total"`
	} `json:"meta"`
}

// Mirrors WorkoutApiTest::index_pages_twenty_workouts_by_default_with_a_tight_envelope
// and ::index_honours_per_page_and_carries_it_into_the_links.
func TestListWorkoutsPagesNewestUpdatedFirst(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	var ids []int64
	for i := range 21 {
		id := f.workout(t, u.ID, "in_progress")
		f.exec(t, "UPDATE workouts SET updated_at = $2 WHERE id = $1", id, start.Add(time.Duration(i)*time.Minute))
		ids = append([]int64{id}, ids...)
	}

	var first page

	decode(t, f.do(u, http.MethodGet, "/api/v1/workouts", nil).AssertStatus(http.StatusOK), &first)

	if len(first.Data) != 20 || first.Meta.PerPage != 20 || first.Meta.LastPage != 2 || first.Data[0].ID != ids[0] {
		t.Errorf("first page: %d items, meta %+v", len(first.Data), first.Meta)
	}

	var second page

	decode(t, f.do(u, http.MethodGet, "/api/v1/workouts?page=2&per_page=5", nil).AssertStatus(http.StatusOK), &second)

	m := second.Meta
	if m.CurrentPage != 2 || m.PerPage != 5 || m.From == nil || *m.From != 6 || m.To == nil || *m.To != 10 || m.Total != 21 || m.LastPage != 5 {
		t.Errorf("meta = %+v", m)
	}

	if got := []int64{second.Data[0].ID, second.Data[4].ID}; !slices.Equal(got, []int64{ids[5], ids[9]}) {
		t.Errorf("page 2 = %v, want %v", got, []int64{ids[5], ids[9]})
	}

	want := map[string]string{
		"first": "http://example.com/api/v1/workouts?page=1&per_page=5",
		"last":  "http://example.com/api/v1/workouts?page=5&per_page=5",
		"prev":  "http://example.com/api/v1/workouts?page=1&per_page=5",
		"next":  "http://example.com/api/v1/workouts?page=3&per_page=5",
	}
	for name, url := range want {
		if got := second.Links[name]; got == nil || *got != url {
			t.Errorf("links.%s = %v, want %s", name, got, url)
		}
	}

	// Far past the end, even past any offset Postgres can count, is empty.
	var beyond page

	decode(t, f.do(u, http.MethodGet, "/api/v1/workouts?page=2147483647&per_page=100", nil).AssertStatus(http.StatusOK), &beyond)

	if len(beyond.Data) != 0 || beyond.Meta.CurrentPage != 2147483647 || beyond.Links["prev"] == nil {
		t.Errorf("a huge page: %d items, meta %+v", len(beyond.Data), beyond.Meta)
	}

	var empty page

	decode(t, f.do(u, http.MethodGet, "/api/v1/workouts?page=9", nil).AssertStatus(http.StatusOK), &empty)

	if len(empty.Data) != 0 || empty.Meta.From != nil || empty.Meta.To != nil || empty.Links["next"] != nil {
		t.Errorf("page past the end: meta %+v, links %v", empty.Meta, empty.Links)
	}
}

// The list embeds each workout's template header and activities count; a
// deleted template is left out.
func TestListWorkoutsEmbedsTemplateAndCount(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	fromTemplate := readWorkout(t, f.do(u, http.MethodPost, "/api/v1/workouts", map[string]any{"workout_template_id": fullBody}, "Accept-Language", "ru")).Data.ID
	f.clock.Advance(time.Minute)
	orphan := readWorkout(t, f.do(u, http.MethodPost, "/api/v1/workouts", map[string]any{"workout_template_id": ride})).Data.ID
	f.exec(t, "DELETE FROM template_activities WHERE template_id = $1", ride)
	f.exec(t, "DELETE FROM workout_templates WHERE id = $1", ride)

	var body page

	decode(t, f.do(u, http.MethodGet, "/api/v1/workouts", nil, "Accept-Language", "ru").AssertStatus(http.StatusOK), &body)

	if body.Data[0].ID != orphan || body.Data[0].WorkoutTemplate != nil || *body.Data[0].ActivitiesCount != 1 {
		t.Errorf("orphan = %+v", body.Data[0])
	}

	if body.Data[1].ID != fromTemplate || body.Data[1].WorkoutTemplate == nil || body.Data[1].WorkoutTemplate.Name != "Всё тело" || *body.Data[1].ActivitiesCount != 2 {
		t.Errorf("from template = %+v", body.Data[1])
	}
}

// Mirrors WorkoutApiTest::index_rejects_an_invalid_per_page, ::index_rejects_an_invalid_page
// and ::index_rejects_an_undeclared_query_parameter.
func TestListWorkoutsRejectsBadQueries(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	for _, tt := range []struct{ query, pointer, code string }{
		{"per_page=0", "/per_page", "out_of_range"},
		{"per_page=101", "/per_page", "out_of_range"},
		{"per_page=abc", "/per_page", "invalid_format"},
		{"page=0", "/page", "out_of_range"},
		{"page=abc", "/page", "invalid_format"},
		{"page=1&sort=name", "/sort", "unknown_field"},
	} {
		t.Run(tt.query, func(t *testing.T) {
			res := f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/workouts?" + tt.query, Token: u.Token})
			fieldErrors(t, res, tt.code, tt.pointer)
		})
	}
}

// Mirrors WorkoutStoreTest::start_creates_workout_and_copies_activities_and_sets_from_template,
// WorkoutApiTest::store_creates_workout_from_template and
// WorkoutConcurrencyTest::show_create_and_complete_return_an_etag (create).
func TestCreateWorkoutCopiesTheTemplate(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	res := f.do(u, http.MethodPost, "/api/v1/workouts", map[string]any{"workout_template_id": fullBody}, "Accept-Language", "ru").
		AssertStatus(http.StatusCreated)
	body := readWorkout(t, res)
	id := body.Data.ID

	res.AssertHeader("ETag", fmt.Sprintf(`"%d-1"`, id))
	res.AssertGolden("create.ru.golden.json")

	// The name is the template's in the request's locale, stored as is.
	if body.Data.Name != "Всё тело" || body.Data.Status != "in_progress" {
		t.Errorf("workout = %+v", body.Data)
	}

	got := f.id(t, `SELECT count(*) FROM workouts WHERE id = $1 AND user_id = $2 AND template_id = $3 AND name = 'Всё тело'`, id, u.ID, fullBody)
	if got != 1 {
		t.Error("the workout row is wrong")
	}

	sets := f.count(t, `SELECT count(*) FROM workout_sets s JOIN workout_activities a ON a.id = s.activity_id
		WHERE a.workout_id = $1 AND NOT s.is_completed AND (
			(a.exercise_id = $2 AND a."order" = 1 AND ((s."order" = 1 AND s.effort_value = 10 AND s.difficulty_value = 20)
				OR (s."order" = 2 AND s.effort_value = 8 AND s.difficulty_value = 22.5)))
			OR (a.exercise_id = $3 AND a."order" = 2 AND s."order" = 1 AND s.effort_value = 15 AND s.difficulty_value IS NULL))`,
		id, benchPress, pushUp)
	if sets != 3 {
		t.Errorf("copied sets matching the template = %d, want 3", sets)
	}

	if f.count(t, "SELECT count(*) FROM template_activities WHERE template_id = $1", fullBody) != 2 {
		t.Error("the template changed")
	}
}

// Mirrors WorkoutStoreTest::requires_workout_template_id_with_custom_message,
// ::requires_existing_workout_template_id_with_custom_message and
// ::rejects_a_boolean_workout_template_id, and WorkoutApiTest::store_rejects_*.
func TestCreateWorkoutRejectsBadTemplates(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	tests := []struct {
		body   any
		code   string
		detail string
	}{
		{map[string]any{}, "required", "A workout template is required to start a workout."},
		{map[string]any{"workout_template_id": 9999}, "not_found", "The selected workout template could not be found."},
		{map[string]any{"workout_template_id": true}, "invalid_format", "The workout template field must be an integer."},
	}

	for _, tt := range tests {
		errs := fieldErrors(t, f.do(u, http.MethodPost, "/api/v1/workouts", tt.body), tt.code, "/workout_template_id")
		if errs["/workout_template_id"] != tt.detail {
			t.Errorf("%v: detail %q, want %q", tt.body, errs["/workout_template_id"], tt.detail)
		}
	}

	if f.count(t, "SELECT count(*) FROM workouts") != 0 {
		t.Error("a workout was created")
	}
}

// Mirrors WorkoutShowTest::owner_can_view_workout and
// WorkoutApiTest::show_returns_workout_with_activities: activities in
// order with their exercises (a retired one still resolves), the count and
// the template header.
func TestGetWorkoutShowsActivitiesExercisesAndTemplate(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")

	id := readWorkout(t, f.do(u, http.MethodPost, "/api/v1/workouts", map[string]any{"workout_template_id": fullBody})).Data.ID
	a := f.activity(t, id, uprightRow, 3)
	f.set(t, a, 1, 12, 30, true)

	res := f.do(u, http.MethodGet, fmt.Sprintf("/api/v1/workouts/%d", id), nil).AssertStatus(http.StatusOK)
	res.AssertHeader("ETag", fmt.Sprintf(`"%d-1"`, id))
	res.AssertGolden("get.en.golden.json")

	body := readWorkout(t, res)
	if names := []string{body.Data.Activities[0].Exercise.Name, body.Data.Activities[2].Exercise.Name}; !slices.Equal(names, []string{"Bench Press", "Upright Row"}) {
		t.Errorf("exercises = %v", names)
	}
}

// Mirrors WorkoutShowTest::non_owner_cannot_view_workout,
// ::show_returns_404_for_nonexistent_workout and ::show_requires_authentication,
// and WorkoutAuthorizationTest::user_cannot_view_another_users_workout_via_api.
func TestGetWorkoutIsOwnerOnly(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	ada, bob := f.signUp(t, "ada@example.com"), f.signUp(t, "bob@example.com")
	id := f.workout(t, ada.ID, "in_progress")

	problem(t, f.do(bob, http.MethodGet, fmt.Sprintf("/api/v1/workouts/%d", id), nil), http.StatusNotFound, "not_found")
	problem(t, f.do(ada, http.MethodGet, "/api/v1/workouts/99999", nil), http.StatusNotFound, "not_found")
	problem(t, f.do(user{}, http.MethodGet, fmt.Sprintf("/api/v1/workouts/%d", id), nil), http.StatusUnauthorized, "unauthenticated")
}

// Every workout operation needs a signed-in user (the *_requires_authentication
// and guest_* tests).
func TestWorkoutOperationsNeedASignedInUser(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	for _, op := range []struct{ method, path string }{
		{http.MethodGet, "/api/v1/workouts"},
		{http.MethodPost, "/api/v1/workouts"},
		{http.MethodGet, "/api/v1/workouts/1"},
		{http.MethodPut, "/api/v1/workouts/1/activities"},
		{http.MethodPost, "/api/v1/workouts/1/complete"},
		{http.MethodPost, "/api/v1/workouts/1/repeat"},
		{http.MethodDelete, "/api/v1/workouts/1"},
	} {
		res := f.api.Do(apitest.Request{Method: op.method, Path: op.path, SkipContract: strings.HasPrefix(op.method, "P")})
		res.AssertStatus(http.StatusUnauthorized)
	}
}

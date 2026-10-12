package http_test

import (
	"fmt"
	"net/http"
	"slices"
	"strings"
	"sync"
	"testing"
)

// The Go counterparts of WorkoutReplaceActivitiesTest and
// WorkoutConcurrencyTest. f.put sends the workout's current ETag, as PHP's
// putActivities helper does.

func set(id any, order, effort int, difficulty any, completed ...bool) map[string]any {
	s := map[string]any{"order": order, "effort_value": effort}
	if id != nil {
		s["id"] = id
	}

	if difficulty != nil {
		s["difficulty_value"] = difficulty
	}

	if len(completed) > 0 {
		s["is_completed"] = completed[0]
	}

	return s
}

// started is an in-progress workout of ada's with one bench-press activity
// holding one set (5 reps at 50).
type started struct {
	f        *fixture
	ada      user
	workout  int64
	activity int64
	set      int64
}

func newStarted(t *testing.T) started {
	t.Helper()

	f := newFixture(t)
	ada := f.signUp(t, "ada@example.com")
	w := f.workout(t, ada.ID, "in_progress")
	a := f.activity(t, w, benchPress, 1)

	return started{f: f, ada: ada, workout: w, activity: a, set: f.set(t, a, 1, 5, 50, false)}
}

func (s started) rows(t *testing.T, query string) []string {
	t.Helper()

	rows, err := s.f.pool.Query(t.Context(), query, s.workout)
	if err != nil {
		t.Fatal(err)
	}
	defer rows.Close()

	var out []string

	for rows.Next() {
		var v string
		if err := rows.Scan(&v); err != nil {
			t.Fatal(err)
		}

		out = append(out, v)
	}

	return out
}

// sets lists the workout's sets as "activity order:exercise:set order:effort:difficulty:completed".
func (s started) sets(t *testing.T) []string {
	t.Helper()

	return s.rows(t, `SELECT a."order" || ':' || a.exercise_id || ':' || st."order" || ':' || st.effort_value || ':' ||
			coalesce(st.difficulty_value::text, '-') || ':' || st.is_completed
		FROM workout_sets st JOIN workout_activities a ON a.id = st.activity_id
		WHERE a.workout_id = $1 ORDER BY a."order", st."order"`)
}

func TestReplaceUpdatesExistingSets(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, benchPress, 1, set(s.set, 1, 10, 80, true)))).
		AssertStatus(http.StatusOK)

	if got := s.sets(t); !slices.Equal(got, []string{"1:1:1:10:80.0:true"}) {
		t.Errorf("sets = %q", got)
	}

	if n := s.f.count(t, "SELECT count(*) FROM workout_sets WHERE id = $1", s.set); n != 1 {
		t.Error("the set was recreated instead of updated")
	}
}

func TestReplaceCreatesNewSetsAndDeletesRemovedOnes(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	removed := s.f.set(t, s.activity, 2, 5, 50, false)

	s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, benchPress, 1, set(s.set, 1, 5, 50), set(nil, 2, 8, 60)))).
		AssertStatus(http.StatusOK)

	if got := s.sets(t); !slices.Equal(got, []string{"1:1:1:5:50.0:false", "1:1:2:8:60.0:false"}) {
		t.Errorf("sets = %q", got)
	}

	if s.f.count(t, "SELECT count(*) FROM workout_sets WHERE id = $1", removed) != 0 {
		t.Error("the removed set is still there")
	}
}

func TestReplaceDeletesActivitiesNotInThePayload(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	second := s.f.activity(t, s.workout, pushUp, 2)
	s.f.set(t, second, 1, 10, nil, false)

	s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, benchPress, 1, set(nil, 1, 5, 50)))).
		AssertStatus(http.StatusOK)

	if s.f.count(t, "SELECT count(*) FROM workout_activities WHERE id = $1", second) != 0 ||
		s.f.count(t, "SELECT count(*) FROM workout_sets s JOIN workout_activities a ON a.id = s.activity_id WHERE a.workout_id = $1", s.workout) != 1 {
		t.Error("the dropped activity or its sets remain")
	}
}

// Mirrors ::save_creates_new_activities and
// ::save_response_exposes_persisted_ids_and_exercise_details_for_new_activities.
func TestReplaceCreatesNewActivitiesAndReturnsTheirIDsAndExercises(t *testing.T) {
	t.Parallel()

	s := newStarted(t)

	res := s.f.put(t, s.ada, s.workout, payload(
		activityPayload(s.activity, benchPress, 1, set(s.set, 1, 5, 50)),
		activityPayload(nil, easyRide, 2, set(nil, 1, 1200, 3)),
	)).AssertStatus(http.StatusOK)
	res.AssertGolden("replace.en.golden.json")

	added := readWorkout(t, res).Data.Activities[1]
	if added.ID == 0 || added.Sets[0].ID == 0 || added.Exercise == nil || added.Exercise.Name != "Easy Ride" ||
		added.Exercise.EquipmentName != "Indoor Bike" || added.Exercise.DifficultyUnit != "heart_rate_zone" {
		t.Errorf("added activity = %+v", added)
	}
}

// Mirrors ::save_appending_an_activity_leaves_the_source_template_untouched.
func TestReplaceLeavesTheSourceTemplateUntouched(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")
	w := readWorkout(t, f.do(u, http.MethodPost, "/api/v1/workouts", map[string]any{"workout_template_id": ride}))
	first := w.Data.ID
	activity := f.id(t, "SELECT id FROM workout_activities WHERE workout_id = $1", first)

	f.put(t, u, first, payload(
		activityPayload(activity, easyRide, 1, set(nil, 1, 600, 2)),
		activityPayload(nil, pushUp, 2, set(nil, 1, 12, nil)),
	)).AssertStatus(http.StatusOK)

	if f.count(t, "SELECT count(*) FROM template_activities WHERE template_id = $1", ride) != 1 ||
		f.count(t, "SELECT count(*) FROM template_activities WHERE exercise_id = $1", pushUp) != 1 {
		t.Error("the template changed")
	}
}

// Mirrors ::save_twice_with_returned_ids_does_not_duplicate_the_added_activity
// and ::save_is_idempotent_when_sent_same_data_twice.
func TestReplaceTwiceWithReturnedIDsDoesNotDuplicate(t *testing.T) {
	t.Parallel()

	s := newStarted(t)

	first := readWorkout(t, s.f.put(t, s.ada, s.workout, payload(
		activityPayload(s.activity, benchPress, 1, set(s.set, 1, 5, 50, true)),
		activityPayload(nil, pushUp, 2, set(nil, 1, 12, nil)),
	)).AssertStatus(http.StatusOK))

	added := first.Data.Activities[1]
	again := payload(
		activityPayload(s.activity, benchPress, 1, set(s.set, 1, 5, 50, true)),
		activityPayload(added.ID, pushUp, 2, set(added.Sets[0].ID, 1, 12, nil)),
	)

	before := s.sets(t)

	s.f.put(t, s.ada, s.workout, again).AssertStatus(http.StatusOK)
	s.f.put(t, s.ada, s.workout, again).AssertStatus(http.StatusOK)

	if after := s.sets(t); !slices.Equal(before, after) || s.f.count(t, "SELECT count(*) FROM workout_activities WHERE workout_id = $1", s.workout) != 2 {
		t.Errorf("sets before %q, after %q", before, after)
	}
}

// Mirrors ::save_handles_complex_diff_multiple_deletes_and_adds and
// ::save_updates_activity_order_as_sent_by_frontend: a payload that drops an
// activity and reorders the rest leaves exactly its activities, ordered 1..n,
// with no unique-key error.
func TestReplaceDropsAndReordersWithoutUniqueViolations(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	second := s.f.activity(t, s.workout, pushUp, 2)
	s.f.set(t, second, 1, 10, nil, false)
	third := s.f.activity(t, s.workout, easyRide, 3)
	thirdSet := s.f.set(t, third, 1, 600, 2, false)

	s.f.put(t, s.ada, s.workout, payload(
		activityPayload(third, easyRide, 1, set(thirdSet, 1, 900, 3)),
		activityPayload(nil, pushUp, 2, set(nil, 1, 20, nil)),
		activityPayload(s.activity, benchPress, 3, set(s.set, 1, 5, 50)),
	)).AssertStatus(http.StatusOK)

	want := []string{"1:2:1:900:3.0:false", "2:3:1:20:-:false", "3:1:1:5:50.0:false"}
	if got := s.sets(t); !slices.Equal(got, want) {
		t.Errorf("sets = %q, want %q", got, want)
	}

	if s.f.count(t, "SELECT count(*) FROM workout_activities WHERE id = $1", second) != 0 {
		t.Error("the dropped activity remains")
	}
}

// Mirrors ::save_preserves_set_order_as_sent_by_frontend: kept sets swap
// orders without a unique-key error.
func TestReplaceSwapsSetOrders(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	other := s.f.set(t, s.activity, 2, 8, 60, false)

	s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, benchPress, 1, set(other, 1, 8, 60), set(s.set, 2, 5, 50)))).
		AssertStatus(http.StatusOK)

	if got := s.sets(t); !slices.Equal(got, []string{"1:1:1:8:60.0:false", "1:1:2:5:50.0:false"}) {
		t.Errorf("sets = %q", got)
	}
}

// Mirrors ::save_replaces_all_activities_with_new_ones.
func TestReplaceReplacesAllActivitiesWithNewOnes(t *testing.T) {
	t.Parallel()

	s := newStarted(t)

	s.f.put(t, s.ada, s.workout, payload(activityPayload(nil, pushUp, 1, set(nil, 1, 10, nil)))).AssertStatus(http.StatusOK)

	if got := s.sets(t); !slices.Equal(got, []string{"1:3:1:10:-:false"}) ||
		s.f.count(t, "SELECT count(*) FROM workout_activities WHERE id = $1", s.activity) != 0 {
		t.Errorf("sets = %q", got)
	}
}

// Mirrors ::save_accepts_sets_that_omit_optional_keys.
func TestReplaceAcceptsSetsWithoutOptionalKeys(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	res := s.f.put(t, s.ada, s.workout, payload(activityPayload(nil, benchPress, 1, set(nil, 1, 10, nil)))).AssertStatus(http.StatusOK)

	got := readWorkout(t, res).Data.Activities[0].Sets[0]
	if got.DifficultyValue != nil || got.IsCompleted {
		t.Errorf("set = %+v", got)
	}
}

// Mirrors ::user_cannot_save_another_users_workout, ::user_cannot_save_completed_workout,
// ::save_nonexistent_workout_returns_404 and
// WorkoutConcurrencyTest::ownership_and_status_are_checked_before_the_precondition.
func TestReplaceChecksOwnershipAndStatusFirst(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	bob := s.f.signUp(t, "bob@example.com")
	body := payload(activityPayload(s.activity, benchPress, 1, set(s.set, 1, 7, 50)))
	path := fmt.Sprintf("/api/v1/workouts/%d/activities", s.workout)

	problem(t, s.f.do(bob, http.MethodPut, path, body), http.StatusNotFound, "not_found")
	problem(t, s.f.put(t, s.ada, 99999, body), http.StatusNotFound, "not_found")

	s.f.exec(t, "UPDATE workouts SET status = 'completed' WHERE id = $1", s.workout)
	problem(t, s.f.do(s.ada, http.MethodPut, path, body), http.StatusForbidden, "forbidden")
	problem(t, s.f.put(t, s.ada, s.workout, body), http.StatusForbidden, "forbidden")

	if got := s.sets(t); !slices.Equal(got, []string{"1:1:1:5:50.0:false"}) {
		t.Errorf("sets changed: %q", got)
	}
}

// Mirrors WorkoutConcurrencyTest::a_matching_if_match_saves_and_returns_a_new_etag,
// ::a_stale_if_match_is_refused_and_nothing_is_written, ::a_missing_if_match_is_refused,
// ::a_weak_tag_never_matches and ::a_wildcard_or_a_list_containing_the_current_tag_matches.
func TestReplaceIfMatch(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	path := fmt.Sprintf("/api/v1/workouts/%d/activities", s.workout)
	body := func(effort int) map[string]any {
		return payload(activityPayload(s.activity, benchPress, 1, set(s.set, 1, effort, 50)))
	}
	tag := func(version int) string { return fmt.Sprintf(`"%d-%d"`, s.workout, version) }

	etag := s.f.do(s.ada, http.MethodGet, fmt.Sprintf("/api/v1/workouts/%d", s.workout), nil).Header().Get("ETag")
	s.f.do(s.ada, http.MethodPut, path, body(7), "If-Match", etag).AssertStatus(http.StatusOK).AssertHeader("ETag", tag(2))

	problem(t, s.f.do(s.ada, http.MethodPut, path, body(9), "If-Match", etag), http.StatusPreconditionFailed, "precondition_failed")
	problem(t, s.f.do(s.ada, http.MethodPut, path, body(9)), http.StatusPreconditionRequired, "precondition_required")
	problem(t, s.f.do(s.ada, http.MethodPut, path, body(9), "If-Match", "W/"+tag(2)), http.StatusPreconditionFailed, "precondition_failed")

	if got := s.sets(t); !slices.Equal(got, []string{"1:1:1:7:50.0:false"}) || s.f.etag(t, s.workout) != tag(2) {
		t.Errorf("a refused replace wrote: %q, %s", got, s.f.etag(t, s.workout))
	}

	s.f.do(s.ada, http.MethodPut, path, body(8), "If-Match", "*").AssertStatus(http.StatusOK).AssertHeader("ETag", tag(3))
	s.f.do(s.ada, http.MethodPut, path, body(9), "If-Match", tag(1)+", "+tag(3)).AssertStatus(http.StatusOK).AssertHeader("ETag", tag(4))
}

// Two replaces of one workout with the same tag: the row lock serialises
// them, one wins, the other gets 412; no unique-key error either way.
func TestConcurrentReplacesSerialise(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	path := fmt.Sprintf("/api/v1/workouts/%d/activities", s.workout)
	tag := s.f.etag(t, s.workout)

	statuses := make([]int, 2)

	var wg sync.WaitGroup

	for i := range 2 {
		wg.Go(func() {
			res := s.f.do(s.ada, http.MethodPut, path, payload(
				activityPayload(nil, pushUp, 1, set(nil, 1, 10+i, nil)),
				activityPayload(s.activity, benchPress, 2, set(s.set, 1, 5, 50)),
			), "If-Match", tag)
			statuses[i] = res.Code
		})
	}

	wg.Wait()
	slices.Sort(statuses)

	if !slices.Equal(statuses, []int{http.StatusOK, http.StatusPreconditionFailed}) {
		t.Errorf("statuses = %v, want one 200 and one 412", statuses)
	}

	if n := s.f.count(t, "SELECT count(*) FROM workout_activities WHERE workout_id = $1", s.workout); n != 2 {
		t.Errorf("activities = %d, want 2", n)
	}
}

// Mirrors ::save_rejects_empty_activities, ::save_rejects_activity_with_no_sets,
// ::save_rejects_set_with_missing_required_fields and
// ::save_rejects_numeric_values_beyond_column_range: the contract's shape,
// with PHP's wording.
func TestReplaceRejectsBadShapes(t *testing.T) {
	t.Parallel()

	s := newStarted(t)

	// An empty list is a missing value, as Laravel's required treats it
	// (PHP's custom min messages never fire).
	errs := fieldErrors(t, s.f.put(t, s.ada, s.workout, payload()), "required", "/activities")
	if errs["/activities"] != "The activities field is required." {
		t.Errorf("detail = %q", errs["/activities"])
	}

	errs = fieldErrors(t, s.f.put(t, s.ada, s.workout, payload(activityPayload(nil, benchPress, 1))), "required", "/activities/0/sets")
	if errs["/activities/0/sets"] != "The sets field is required." {
		t.Errorf("detail = %q", errs["/activities/0/sets"])
	}

	errs = fieldErrors(t, s.f.put(t, s.ada, s.workout, payload(activityPayload(nil, benchPress, 1,
		map[string]any{"order": nil, "effort_value": nil, "difficulty_value": nil}))), "",
		"/activities/0/sets/0/order", "/activities/0/sets/0/effort_value")
	if errs["/activities/0/sets/0/effort_value"] != "The effort value field is required." {
		t.Errorf("detail = %q", errs["/activities/0/sets/0/effort_value"])
	}

	fieldErrors(t, s.f.put(t, s.ada, s.workout, payload(activityPayload(nil, benchPress, 1001,
		set(nil, 58601485020608, 100001, 123456.5)))), "out_of_range",
		"/activities/0/order", "/activities/0/sets/0/order", "/activities/0/sets/0/effort_value", "/activities/0/sets/0/difficulty_value")

	if got := s.sets(t); !slices.Equal(got, []string{"1:1:1:5:50.0:false"}) || s.f.etag(t, s.workout) != fmt.Sprintf(`"%d-1"`, s.workout) {
		t.Errorf("a rejected replace wrote: %q", got)
	}
}

// Mirrors ::save_rejects_invalid_exercise_id, ::save_rejects_activity_id_belonging_to_another_workout
// and ::save_handles_set_id_belonging_to_different_activity_in_same_workout,
// with a pointer per id.
func TestReplaceRejectsIDsThatAreNotTheWorkouts(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	other := s.f.workout(t, s.ada.ID, "in_progress")
	foreign := s.f.activity(t, other, benchPress, 1)
	second := s.f.activity(t, s.workout, pushUp, 2)
	secondSet := s.f.set(t, second, 1, 10, nil, false)

	errs := fieldErrors(t, s.f.put(t, s.ada, s.workout, payload(
		activityPayload(s.activity, benchPress, 1, set(secondSet, 1, 5, 50)),
		activityPayload(foreign, 99999, 2, set(nil, 1, 5, nil)),
		activityPayload(second, pushUp, 3, set(s.set, 1, 10, nil)),
	)), "not_found", "/activities/0/sets/0/id", "/activities/1/id", "/activities/1/exercise_id", "/activities/2/sets/0/id")

	if errs["/activities/1/exercise_id"] != "The selected exercise is invalid." || errs["/activities/1/id"] != "The selected activities.1.id is invalid." {
		t.Errorf("details = %q", errs)
	}
}

// Activity ids, activity orders, set ids and set orders within an activity
// must not repeat. PHP checks the first three in the request; a repeated set
// order reached its unique key as a 500.
func TestReplaceRejectsRepeats(t *testing.T) {
	t.Parallel()

	s := newStarted(t)

	errs := fieldErrors(t, s.f.put(t, s.ada, s.workout, payload(
		activityPayload(s.activity, benchPress, 1, set(s.set, 1, 5, 50), set(nil, 1, 6, 50)),
		activityPayload(s.activity, benchPress, 1, set(s.set, 2, 5, 50)),
	)), "not_unique",
		"/activities/0/id", "/activities/1/id", "/activities/0/order", "/activities/1/order",
		"/activities/0/sets/0/id", "/activities/1/sets/0/id", "/activities/0/sets/0/order", "/activities/0/sets/1/order")

	if errs["/activities/0/order"] != "The activity order field has a duplicate value." {
		t.Errorf("detail = %q", errs["/activities/0/order"])
	}
}

// Mirrors ::save_rejects_completed_set_with_zero_effort.
func TestReplaceRejectsACompletedSetWithZeroEffort(t *testing.T) {
	t.Parallel()

	s := newStarted(t)

	errs := fieldErrors(t, s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, benchPress, 1, set(s.set, 1, 0, 50, true)))),
		"invalid_value", "/activities/0/sets/0/is_completed")
	if errs["/activities/0/sets/0/is_completed"] != "A set cannot be marked as completed with 0 effort." {
		t.Errorf("detail = %q", errs)
	}

	s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, benchPress, 1, set(s.set, 1, 0, 50, false)))).AssertStatus(http.StatusOK)
}

// Mirrors ::save_accepts_a_valid_heart_rate_zone, ::save_allows_null_zone_for_a_zone_exercise,
// ::save_rejects_a_zone_above_five, ::save_rejects_a_zone_below_one and
// ::save_allows_large_weight_for_non_zone_exercise.
func TestReplaceChecksHeartRateZones(t *testing.T) {
	t.Parallel()

	s := newStarted(t)

	for _, tt := range []struct {
		exercise   int64
		difficulty any
		ok         bool
	}{
		{easyRide, 3, true},
		{easyRide, nil, true},
		{easyRide, 6, false},
		{easyRide, 0, false},
		{easyRide, 2.5, false},
		{benchPress, 120, true},
	} {
		res := s.f.put(t, s.ada, s.workout, payload(activityPayload(nil, tt.exercise, 1, set(nil, 1, 600, tt.difficulty))))

		if tt.ok {
			res.AssertStatus(http.StatusOK)

			continue
		}

		errs := fieldErrors(t, res, "out_of_range", "/activities/0/sets/0/difficulty_value")
		if errs["/activities/0/sets/0/difficulty_value"] != "Heart-rate zone must be between 1 and 5." {
			t.Errorf("%v: detail = %q", tt.difficulty, errs)
		}
	}
}

// A retired exercise stays on the activity that has it, but cannot be added
// (ADR 0010; PHP's exists rule accepted it).
func TestReplaceRejectsARetiredExerciseOnANewActivity(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	retired := s.f.activity(t, s.workout, uprightRow, 2)
	retiredSet := s.f.set(t, retired, 1, 10, 20, false)

	keep := activityPayload(retired, uprightRow, 2, set(retiredSet, 1, 12, 20))
	s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, benchPress, 1, set(s.set, 1, 5, 50)), keep)).AssertStatus(http.StatusOK)

	fieldErrors(t, s.f.put(t, s.ada, s.workout, payload(keep, activityPayload(nil, uprightRow, 1, set(nil, 1, 5, 20)))),
		"not_found", "/activities/1/exercise_id")
	fieldErrors(t, s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, uprightRow, 1, set(s.set, 1, 5, 50)), keep)),
		"not_found", "/activities/0/exercise_id")
}

// An existing activity may change its exercise (PHP kept the old one and
// ignored the new id; ADR 0010).
func TestReplaceChangesAnActivitysExercise(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	s.f.put(t, s.ada, s.workout, payload(activityPayload(s.activity, pushUp, 1, set(s.set, 1, 5, nil)))).AssertStatus(http.StatusOK)

	if got := s.sets(t); !slices.Equal(got, []string{"1:3:1:5:-:false"}) {
		t.Errorf("sets = %q", got)
	}
}

// Mirrors WorkoutConcurrencyTest::the_old_save_endpoint_is_gone.
func TestTheOldSaveEndpointIsGone(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	res := s.f.do(s.ada, http.MethodPatch, fmt.Sprintf("/api/v1/workouts/%d/save", s.workout), payload())

	if res.Code != http.StatusNotFound || !strings.Contains(res.Body.String(), "not_found") {
		t.Errorf("PATCH …/save = %d %s", res.Code, res.Body)
	}
}

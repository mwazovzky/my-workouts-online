package http_test

import (
	"fmt"
	"net/http"
	"testing"
	"time"
)

// Mirrors WorkoutCompleteTest::complete_workout_changes_status_to_completed,
// WorkoutApiTest::complete_marks_workout_as_completed and
// WorkoutConcurrencyTest::show_create_and_complete_return_an_etag (complete).
func TestCompleteWorkout(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	s.f.clock.Advance(10 * time.Minute)

	res := s.f.do(s.ada, http.MethodPost, fmt.Sprintf("/api/v1/workouts/%d/complete", s.workout), nil).AssertStatus(http.StatusOK)
	res.AssertHeader("ETag", fmt.Sprintf(`"%d-2"`, s.workout))
	res.AssertGolden("complete.golden.json")

	if got := s.rows(t, "SELECT status || ' ' || updated_at::text FROM workouts WHERE id = $1"); got[0] != "completed 2026-10-12 09:10:00+00" {
		t.Errorf("workout = %q", got)
	}
}

// Mirrors WorkoutCompleteTest::user_cannot_complete_another_users_workout,
// ::user_cannot_complete_already_completed_workout and
// ::user_cannot_complete_nonexistent_workout, and
// WorkoutAuthorizationTest::user_cannot_complete_another_users_workout.
func TestCompleteWorkoutIsOwnerOnlyAndOnce(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	bob := s.f.signUp(t, "bob@example.com")
	path := fmt.Sprintf("/api/v1/workouts/%d/complete", s.workout)

	problem(t, s.f.do(bob, http.MethodPost, path, nil), http.StatusNotFound, "not_found")

	if got := s.rows(t, "SELECT status FROM workouts WHERE id = $1"); got[0] != "in_progress" {
		t.Error("the other user completed the workout")
	}

	problem(t, s.f.do(s.ada, http.MethodPost, "/api/v1/workouts/99999/complete", nil), http.StatusNotFound, "not_found")

	s.f.do(s.ada, http.MethodPost, path, nil).AssertStatus(http.StatusOK)
	problem(t, s.f.do(s.ada, http.MethodPost, path, nil), http.StatusForbidden, "forbidden")

	if tag := s.f.etag(t, s.workout); tag != fmt.Sprintf(`"%d-2"`, s.workout) {
		t.Errorf("etag after a refused complete = %s", tag)
	}
}

// Mirrors WorkoutRepeatTest::owner_can_repeat_a_completed_workout_and_it_copies_activities_and_sets_and_resets_completion
// and WorkoutApiTest::repeat_creates_new_in_progress_workout.
func TestRepeatWorkoutCopiesItUncompleted(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	u := f.signUp(t, "ada@example.com")
	source := readWorkout(t, f.do(u, http.MethodPost, "/api/v1/workouts", map[string]any{"workout_template_id": fullBody})).Data.ID
	f.exec(t, "UPDATE workout_sets SET is_completed = true WHERE activity_id IN (SELECT id FROM workout_activities WHERE workout_id = $1)", source)
	f.do(u, http.MethodPost, fmt.Sprintf("/api/v1/workouts/%d/complete", source), nil).AssertStatus(http.StatusOK)
	f.clock.Advance(10 * time.Minute)

	res := f.do(u, http.MethodPost, fmt.Sprintf("/api/v1/workouts/%d/repeat", source), nil).AssertStatus(http.StatusCreated)
	body := readWorkout(t, res)
	copied := body.Data.ID

	res.AssertHeader("ETag", fmt.Sprintf(`"%d-1"`, copied))

	if copied == source || body.Data.Status != "in_progress" || body.Data.Name != "Full Body" || body.Data.WorkoutTemplate != nil {
		t.Errorf("copy = %+v", body.Data)
	}

	if f.count(t, "SELECT count(*) FROM workouts WHERE id = $1 AND template_id IS NULL", copied) != 1 {
		t.Error("the copy kept the template link")
	}

	shape := `SELECT string_agg(a."order" || ':' || a.exercise_id || ':' || s."order" || ':' || s.effort_value || ':' ||
			coalesce(s.difficulty_value::text, '-'), ',' ORDER BY a."order", s."order")
		FROM workout_sets s JOIN workout_activities a ON a.id = s.activity_id WHERE a.workout_id = $1`

	var want, got string
	if err := f.pool.QueryRow(t.Context(), shape, source).Scan(&want); err != nil {
		t.Fatal(err)
	}

	if err := f.pool.QueryRow(t.Context(), shape, copied).Scan(&got); err != nil {
		t.Fatal(err)
	}

	if got != want {
		t.Errorf("copied sets %q, source %q", got, want)
	}

	if f.count(t, `SELECT count(*) FROM workout_sets s JOIN workout_activities a ON a.id = s.activity_id
		WHERE a.workout_id = $1 AND s.is_completed`, copied) != 0 {
		t.Error("a copied set is completed")
	}
}

// Mirrors WorkoutRepeatTest::non_owner_cannot_repeat_another_users_workout and
// ::owner_cannot_repeat_an_in_progress_workout.
func TestRepeatWorkoutIsOwnerOnlyAndNeedsACompletedWorkout(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	bob := s.f.signUp(t, "bob@example.com")
	path := fmt.Sprintf("/api/v1/workouts/%d/repeat", s.workout)

	problem(t, s.f.do(s.ada, http.MethodPost, path, nil), http.StatusForbidden, "forbidden")

	s.f.exec(t, "UPDATE workouts SET status = 'completed' WHERE id = $1", s.workout)
	problem(t, s.f.do(bob, http.MethodPost, path, nil), http.StatusNotFound, "not_found")

	if n := s.f.count(t, "SELECT count(*) FROM workouts"); n != 1 {
		t.Errorf("workouts = %d, want 1", n)
	}
}

// Mirrors WorkoutDeleteTest::owner_can_delete_workout and
// WorkoutApiTest::destroy_deletes_the_workout.
func TestDeleteWorkoutCascades(t *testing.T) {
	t.Parallel()

	s := newStarted(t)

	s.f.do(s.ada, http.MethodDelete, fmt.Sprintf("/api/v1/workouts/%d", s.workout), nil).AssertStatus(http.StatusNoContent)

	if s.f.count(t, "SELECT count(*) FROM workouts")+s.f.count(t, "SELECT count(*) FROM workout_activities")+
		s.f.count(t, "SELECT count(*) FROM workout_sets") != 0 {
		t.Error("rows remain")
	}
}

// Mirrors WorkoutDeleteTest::non_owner_cannot_delete_workout and
// WorkoutApiTest::destroy_returns_404_for_another_users_workout.
func TestDeleteWorkoutIsOwnerOnly(t *testing.T) {
	t.Parallel()

	s := newStarted(t)
	bob := s.f.signUp(t, "bob@example.com")

	problem(t, s.f.do(bob, http.MethodDelete, fmt.Sprintf("/api/v1/workouts/%d", s.workout), nil), http.StatusNotFound, "not_found")

	if s.f.count(t, "SELECT count(*) FROM workout_sets") != 1 {
		t.Error("the other user deleted the workout")
	}
}

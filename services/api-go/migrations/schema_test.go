package migrations_test

import (
	"context"
	"testing"

	"github.com/jackc/pgx/v5/pgxpool"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

// fixture is one user, one piece of equipment, one exercise, a template with
// one activity and set, a program scheduling it, and a workout from it. Every
// id is 1.
const fixture = `
INSERT INTO users (name, email, password_hash) VALUES ('Ada', 'ada@example.com', 'hash');
INSERT INTO equipment (slug, name) VALUES ('barbell', 'Barbell');
INSERT INTO exercises (slug, name, equipment_id) VALUES ('squat', 'Squat', 1);
INSERT INTO workout_templates (slug, name) VALUES ('legs', 'Legs');
INSERT INTO template_activities (template_id, exercise_id, "order") VALUES (1, 1, 1);
INSERT INTO template_sets (activity_id, "order", effort_value, difficulty_value) VALUES (1, 1, 5, 60);
INSERT INTO programs (slug, name) VALUES ('strength', 'Strength');
INSERT INTO program_workout_template (program_id, template_id, weekday) VALUES (1, 1, 'monday');
INSERT INTO program_user (program_id, user_id) VALUES (1, 1);
INSERT INTO workouts (user_id, template_id, name) VALUES (1, 1, 'Legs');
INSERT INTO workout_activities (workout_id, exercise_id, "order") VALUES (1, 1, 1);
INSERT INTO workout_sets (activity_id, "order", effort_value, difficulty_value) VALUES (1, 1, 5, 60);
`

func seeded(t *testing.T) *pgxpool.Pool {
	t.Helper()

	pool := testdb.New(t)
	if _, err := pool.Exec(t.Context(), fixture); err != nil {
		t.Fatalf("fixture: %v", err)
	}

	return pool
}

func TestConstraints(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name       string
		statement  string
		kind       apperr.Kind
		constraint string
	}{
		{
			name:       "enrolling twice",
			statement:  "INSERT INTO program_user (program_id, user_id) VALUES (1, 1)",
			kind:       apperr.KindConflict,
			constraint: "program_user_pkey",
		},
		{
			name:       "two template sets in one position",
			statement:  `INSERT INTO template_sets (activity_id, "order", effort_value) VALUES (1, 1, 8)`,
			kind:       apperr.KindConflict,
			constraint: "template_sets_activity_id_order_key",
		},
		{
			name:       "two workout sets in one position",
			statement:  `INSERT INTO workout_sets (activity_id, "order", effort_value) VALUES (1, 1, 8)`,
			kind:       apperr.KindConflict,
			constraint: "workout_sets_activity_id_order_key",
		},
		{
			name:       "two workout activities in one position",
			statement:  `INSERT INTO workout_activities (workout_id, exercise_id, "order") VALUES (1, 1, 1)`,
			kind:       apperr.KindConflict,
			constraint: "workout_activities_workout_id_order_key",
		},
		{
			name:       "a second user with the same email in other case",
			statement:  "INSERT INTO users (name, email, password_hash) VALUES ('Ada', 'ADA@example.com', 'hash')",
			kind:       apperr.KindConflict,
			constraint: "users_email_key",
		},
		{
			name:       "deleting equipment an exercise uses",
			statement:  "DELETE FROM equipment WHERE id = 1",
			kind:       apperr.KindConflict,
			constraint: "exercises_equipment_id_fkey",
		},
		{
			name:       "deleting a template a program schedules",
			statement:  "DELETE FROM workout_templates WHERE id = 1",
			kind:       apperr.KindConflict,
			constraint: "program_workout_template_template_id_fkey",
		},
		{
			name:       "hard-deleting an exercise with history",
			statement:  "DELETE FROM exercises WHERE id = 1",
			kind:       apperr.KindConflict,
			constraint: "template_activities_exercise_id_fkey",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			_, err := seeded(t).Exec(t.Context(), tt.statement)

			mapped := postgres.MapError(err)
			if !apperr.HasKind(mapped, tt.kind) {
				t.Fatalf("error = %v, want %v", mapped, tt.kind)
			}

			if got := postgres.ConstraintName(mapped); got != tt.constraint {
				t.Errorf("constraint = %q, want %q", got, tt.constraint)
			}
		})
	}
}

func TestCheckConstraintsRejectValuesOutsideTheContract(t *testing.T) {
	t.Parallel()

	for _, statement := range []string{
		"UPDATE program_workout_template SET weekday = 'Monday'",
		"UPDATE workouts SET status = 'paused'",
		"UPDATE equipment SET difficulty_unit = 'stones'",
		"UPDATE exercises SET effort_type = 'distance'",
		"UPDATE users SET theme_preference = 'sepia'",
		"UPDATE workouts SET version = 0",
		"UPDATE workout_sets SET effort_value = -1",
		"UPDATE programs SET translations = '[]'",
	} {
		if _, err := seeded(t).Exec(t.Context(), statement); err == nil {
			t.Errorf("%s: error = nil, want a check violation", statement)
		}
	}
}

func TestDeletesCascadeOrDetachAsDeclared(t *testing.T) {
	t.Parallel()

	pool := seeded(t)
	ctx := t.Context()

	// The schedule goes first; then the template can go, and the workout
	// keeps its history without it.
	mustExec(t, pool, "DELETE FROM program_workout_template")
	mustExec(t, pool, "DELETE FROM workout_templates WHERE id = 1")

	var templateID *int64
	if err := pool.QueryRow(ctx, "SELECT template_id FROM workouts WHERE id = 1").Scan(&templateID); err != nil {
		t.Fatal(err)
	}

	if templateID != nil {
		t.Errorf("workouts.template_id = %d, want NULL after the template is deleted", *templateID)
	}

	if n := count(ctx, t, pool, "template_sets"); n != 0 {
		t.Errorf("template_sets = %d, want the template's sets cascaded away", n)
	}

	// Deleting the user removes their workouts, sets and enrollments.
	mustExec(t, pool, "DELETE FROM users WHERE id = 1")

	for _, table := range []string{"workouts", "workout_activities", "workout_sets", "program_user"} {
		if n := count(ctx, t, pool, table); n != 0 {
			t.Errorf("%s = %d rows, want 0 after the user is deleted", table, n)
		}
	}
}

func TestAProgramMayScheduleATemplateTwice(t *testing.T) {
	t.Parallel()

	pool := seeded(t)
	mustExec(t, pool, "INSERT INTO program_workout_template (program_id, template_id, weekday) VALUES (1, 1, 'monday'), (1, 1, 'thursday')")

	if n := count(t.Context(), t, pool, "program_workout_template"); n != 3 {
		t.Errorf("program_workout_template = %d rows, want 3", n)
	}
}

func TestDifficultyValueRoundTripsExactly(t *testing.T) {
	t.Parallel()

	pool := seeded(t)
	mustExec(t, pool, "UPDATE workout_sets SET difficulty_value = 12.5")

	var text string
	if err := pool.QueryRow(t.Context(), "SELECT difficulty_value::text FROM workout_sets").Scan(&text); err != nil {
		t.Fatal(err)
	}

	if text != "12.5" {
		t.Errorf("difficulty_value = %s, want 12.5", text)
	}
}

func mustExec(t *testing.T, pool *pgxpool.Pool, statement string) {
	t.Helper()

	if _, err := pool.Exec(t.Context(), statement); err != nil {
		t.Fatalf("%s: %v", statement, err)
	}
}

func count(ctx context.Context, t *testing.T, pool *pgxpool.Pool, table string) int {
	t.Helper()

	var n int
	if err := pool.QueryRow(ctx, "SELECT count(*) FROM "+table).Scan(&n); err != nil {
		t.Fatal(err)
	}

	return n
}

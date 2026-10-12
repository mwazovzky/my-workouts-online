package postgres_test

import (
	"log/slog"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
	trainingpg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/adapters/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/app"
)

func TestMain(m *testing.M) { testdb.Main(m) }

var start = time.Date(2026, 10, 12, 9, 0, 0, 0, time.UTC)

// The trainingapi surface the dashboard reads, over the real repository.
func TestTrainingAPI(t *testing.T) {
	t.Parallel()

	pool := testdb.New(t)
	ctx := t.Context()
	tx := postgres.NewTransactor(pool)
	service := app.New(app.Deps{
		Workouts: trainingpg.NewWorkouts(tx), Tx: tx,
		Clock: clock.NewFake(start), Logger: slog.New(slog.DiscardHandler),
	})

	exec := func(sql string, args ...any) int64 {
		var id int64
		if err := pool.QueryRow(ctx, sql, args...).Scan(&id); err != nil {
			t.Fatal(err)
		}

		return id
	}

	ada := exec("INSERT INTO users (name, email, password_hash) VALUES ('Ada', 'ada@example.com', 'x') RETURNING id")
	bob := exec("INSERT INTO users (name, email, password_hash) VALUES ('Bob', 'bob@example.com', 'x') RETURNING id")
	exec("INSERT INTO equipment (name) VALUES ('Barbell') RETURNING id")
	exercise := exec("INSERT INTO exercises (name, equipment_id) VALUES ('Squat', 1) RETURNING id")

	workout := func(userID int64, status string, updated time.Time) int64 {
		return exec("INSERT INTO workouts (user_id, name, status, created_at, updated_at) VALUES ($1, 'W', $2, $3, $3) RETURNING id",
			userID, status, updated)
	}

	workout(ada, "completed", start.Add(-10*24*time.Hour))
	recent := workout(ada, "completed", start.Add(-2*24*time.Hour))
	open := workout(ada, "in_progress", start.Add(-time.Hour))
	workout(bob, "in_progress", start)
	exec(`INSERT INTO workout_activities (workout_id, exercise_id, "order") VALUES ($1, $2, 1) RETURNING id`, open, exercise)

	summaries, err := service.RecentWorkouts(ctx, ada, 2)
	if err != nil {
		t.Fatal(err)
	}

	if len(summaries) != 2 || summaries[0].ID != open || summaries[1].ID != recent || summaries[0].ActivitiesCount != 1 {
		t.Errorf("recent = %+v", summaries)
	}

	inProgress, ok, err := service.InProgress(ctx, ada)
	if err != nil || !ok || inProgress.ID != open || inProgress.Status != "in_progress" {
		t.Errorf("in progress = %+v, %v, %v", inProgress, ok, err)
	}

	counts, err := service.Counts(ctx, ada, start.Add(-7*24*time.Hour))
	if err != nil || counts.Completed != 2 || counts.CompletedSince != 1 {
		t.Errorf("counts = %+v, %v; want 2 in all, 1 in the last week", counts, err)
	}

	if _, ok, err := service.InProgress(ctx, exec("INSERT INTO users (name, email, password_hash) VALUES ('Cy', 'cy@example.com', 'x') RETURNING id")); ok || err != nil {
		t.Errorf("a user without workouts has one in progress: %v, %v", ok, err)
	}
}

package http_test

import (
	"context"
	_ "embed"
	"encoding/json"
	"log/slog"
	"net/http"
	"slices"
	"testing"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

//go:embed testdata/catalog.sql
var catalogSQL string

// The key pair of services/api-php/phpunit.xml.
var testJWT = config.JWT{
	Issuer:            "http://localhost",
	Audience:          "my-workouts-test",
	PrivateKey:        "SNdNP8JHR4IZdp8IesFeqOLNQr2l8U05g/h7h4l2ho8nsYzYswZYM40Mn8Ez9bAYRKT96SVgnnDdHOK7M+khjg==",
	PublicKey:         "J7GM2LMGWDONDJ/BM/WwGESk/eklYJ5w3RziuzPpIY4=",
	AccessTTLSeconds:  900,
	RefreshTTLSeconds: 2592000,
	LeewaySeconds:     30,
}

// now is DashboardApiTest's frozen time: Monday 2025-01-06 10:00 UTC.
var now = time.Date(2025, 1, 6, 10, 0, 0, 0, time.UTC)

type fixture struct {
	api  *apitest.Client
	pool *pgxpool.Pool
}

type user struct {
	ID    int64
	Token string
}

func newFixture(t *testing.T) (*fixture, user) {
	t.Helper()

	f := &fixture{pool: testdb.New(t)}

	app, err := bootstrap.New(bootstrap.Deps{
		Config: config.Config{
			AppURL: "http://localhost:8080", RateLimitPerMinute: 100_000, AppEnv: "testing", BcryptRounds: 4, JWT: testJWT,
			AppName: "My Workouts", FrontendURL: "https://app.example.test", LinkSigningKey: "test-link-signing-key",
		},
		Logger: slog.New(slog.DiscardHandler),
		Clock:  clock.NewFake(now),
		Pool:   f.pool,
		Mailer: &mail.Fake{},
	})
	if err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { _ = app.Close(context.Background()) })

	f.api = apitest.New(t, app.Handler)
	f.exec(t, catalogSQL)

	res := f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/register", Body: map[string]any{
		"name": "Ada", "email": "ada@example.com", "password": "secret123", "client": "native",
	}}).AssertStatus(http.StatusCreated)

	var body struct {
		AccessToken string `json:"access_token"`
		User        struct {
			ID int64 `json:"id"`
		} `json:"user"`
	}

	if err := json.Unmarshal(res.Body.Bytes(), &body); err != nil {
		t.Fatal(err)
	}

	f.exec(t, "UPDATE users SET email_verified_at = now() WHERE id = $1", body.User.ID)

	return f, user{ID: body.User.ID, Token: body.AccessToken}
}

func (f *fixture) exec(t *testing.T, sql string, args ...any) {
	t.Helper()

	if _, err := f.pool.Exec(t.Context(), sql, args...); err != nil {
		t.Fatal(err)
	}
}

func (f *fixture) enroll(t *testing.T, u user, programs ...int) {
	t.Helper()

	for _, p := range programs {
		f.exec(t, "INSERT INTO program_user (program_id, user_id) VALUES ($1, $2)", p, u.ID)
	}
}

// workout inserts a workout of u's, updated at the given time.
func (f *fixture) workout(t *testing.T, u user, status string, template any, updated time.Time) int64 {
	t.Helper()

	var id int64
	if err := f.pool.QueryRow(t.Context(),
		"INSERT INTO workouts (user_id, template_id, name, status, created_at, updated_at) VALUES ($1, $2, 'Morning', $3, $4, $4) RETURNING id",
		u.ID, template, status, updated).Scan(&id); err != nil {
		t.Fatal(err)
	}

	return id
}

func (f *fixture) dashboard(t *testing.T, u user, locale string) (*apitest.Response, dashboard) {
	t.Helper()

	res := f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/dashboard", Token: u.Token, Locale: locale}).
		AssertStatus(http.StatusOK)

	var body struct {
		Data dashboard `json:"data"`
	}

	if err := json.Unmarshal(res.Body.Bytes(), &body); err != nil {
		t.Fatal(err)
	}

	return res, body.Data
}

type dashboard struct {
	UpcomingSchedule []struct {
		ID           string  `json:"id"`
		ProgramID    int64   `json:"program_id"`
		ProgramName  *string `json:"program_name"`
		WorkoutName  *string `json:"workout_name"`
		Weekday      string  `json:"weekday"`
		ScheduledAt  string  `json:"scheduled_at"`
		ScheduledFor string  `json:"scheduled_for"`
	} `json:"upcoming_schedule"`
	InProgressWorkout *struct {
		ID int64 `json:"id"`
	} `json:"in_progress_workout"`
	RecentWorkouts []struct {
		ID     int64  `json:"id"`
		Status string `json:"status"`
	} `json:"recent_workouts"`
	Summary map[string]int `json:"summary"`
}

// Mirrors DashboardApiTest::index_returns_dashboard_data and
// ::index_omits_in_progress_workout_when_none_exists: the key is absent,
// not null.
func TestDashboardForANewUser(t *testing.T) {
	t.Parallel()

	f, u := newFixture(t)
	res, d := f.dashboard(t, u, "")

	res.AssertGolden("empty.golden.json")

	var raw map[string]map[string]json.RawMessage
	if err := json.Unmarshal(res.Body.Bytes(), &raw); err != nil {
		t.Fatal(err)
	}

	if _, ok := raw["data"]["in_progress_workout"]; ok {
		t.Error("in_progress_workout is present")
	}

	if len(d.UpcomingSchedule) != 0 || len(d.RecentWorkouts) != 0 || d.Summary["enrolled_programs_count"] != 0 {
		t.Errorf("dashboard = %+v", d)
	}
}

// Mirrors DashboardApiTest::index_includes_upcoming_schedule_for_enrolled_programs
// and the story's shared-template case: two programs with Full Body on
// Monday give two items for today with distinct program ids; names are
// localized, weekdays stay the enum.
func TestDashboardSchedule(t *testing.T) {
	t.Parallel()

	f, u := newFixture(t)
	f.enroll(t, u, 1, 2, 3)

	res, d := f.dashboard(t, u, "ru")
	res.AssertGolden("schedule.ru.golden.json")

	var ids []string
	for _, s := range d.UpcomingSchedule {
		ids = append(ids, s.ID)
	}

	want := []string{"1-1-2025-01-06", "2-1-2025-01-06", "1-2-2025-01-08", "2-2-2025-01-12"}
	if !slices.Equal(ids, want) {
		t.Errorf("ids = %v, want %v", ids, want)
	}

	first := d.UpcomingSchedule[0]
	if *first.ProgramName != "Сила" || *first.WorkoutName != "Всё тело" || first.Weekday != "monday" ||
		first.ScheduledAt != "2025-01-06T00:00:00Z" || first.ScheduledFor != "2025-01-06" {
		t.Errorf("first item = %+v", first)
	}

	if d.Summary["enrolled_programs_count"] != 3 || d.Summary["upcoming_workouts_count"] != 4 {
		t.Errorf("summary = %v", d.Summary)
	}
}

// Mirrors DashboardApiTest::index_includes_in_progress_workout,
// ::index_returns_correct_summary_counts and the story's window bounds: a
// workout completed 6 days 23 hours ago counts, one 7 days 1 hour ago does
// not; recent workouts are the 5 newest, of any status.
func TestDashboardWorkoutsAndCounts(t *testing.T) {
	t.Parallel()

	f, u := newFixture(t)
	f.enroll(t, u, 1)

	f.workout(t, u, "completed", 1, now.Add(-(7*24+1)*time.Hour))
	inside := f.workout(t, u, "completed", 1, now.Add(-(6*24+23)*time.Hour))
	older := f.workout(t, u, "in_progress", nil, now.Add(-3*time.Hour))
	newest := f.workout(t, u, "in_progress", 1, now.Add(-time.Hour))

	for i := range 3 {
		f.workout(t, u, "completed", 2, now.Add(-time.Duration(30+i)*24*time.Hour))
	}

	res, d := f.dashboard(t, u, "")
	res.AssertGolden("workouts.golden.json")

	if d.InProgressWorkout == nil || d.InProgressWorkout.ID != newest {
		t.Errorf("in progress = %+v, want %d", d.InProgressWorkout, newest)
	}

	var recent []int64
	for _, w := range d.RecentWorkouts {
		recent = append(recent, w.ID)
	}

	if len(recent) != 5 || recent[0] != newest || recent[1] != older || recent[2] != inside {
		t.Errorf("recent = %v", recent)
	}

	want := map[string]int{"enrolled_programs_count": 1, "completed_workouts_count": 5, "completed_last_7_days_count": 1, "upcoming_workouts_count": 2}
	for k, v := range want {
		if d.Summary[k] != v {
			t.Errorf("summary.%s = %d, want %d", k, d.Summary[k], v)
		}
	}
}

// Mirrors the dashboard half of ProgramEnrollTest::unenroll_only_removes_the_callers_enrollment_and_keeps_their_workouts.
func TestDashboardAfterUnenrolling(t *testing.T) {
	t.Parallel()

	f, u := newFixture(t)
	f.enroll(t, u, 1)
	f.workout(t, u, "in_progress", 1, now)

	f.api.Do(apitest.Request{Method: http.MethodDelete, Path: "/api/v1/programs/1/enrollment", Token: u.Token}).
		AssertStatus(http.StatusNoContent)

	_, d := f.dashboard(t, u, "")
	if d.Summary["enrolled_programs_count"] != 0 || len(d.UpcomingSchedule) != 0 || len(d.RecentWorkouts) != 1 {
		t.Errorf("dashboard = %+v", d)
	}
}

// Mirrors DashboardApiTest::index_requires_authentication; an unverified
// user gets 403.
func TestDashboardNeedsAVerifiedUser(t *testing.T) {
	t.Parallel()

	f, u := newFixture(t)

	f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/dashboard"}).AssertStatus(http.StatusUnauthorized)

	f.exec(t, "UPDATE users SET email_verified_at = NULL WHERE id = $1", u.ID)
	f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/dashboard", Token: u.Token}).AssertStatus(http.StatusForbidden)
}

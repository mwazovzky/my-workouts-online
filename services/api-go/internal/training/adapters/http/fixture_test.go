package http_test

import (
	"context"
	_ "embed"
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
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

// The catalog in testdata/catalog.sql.
const (
	benchPress = 1 // barbell, kilograms
	easyRide   = 2 // indoor bike, heart-rate zone
	pushUp     = 3 // bodyweight, no difficulty
	uprightRow = 4 // retired
	fullBody   = 1 // bench press (2 sets), push-up (1 set)
	ride       = 2 // easy ride (1 set, zone 2)
)

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

var start = time.Date(2026, 10, 12, 9, 0, 0, 0, time.UTC)

type fixture struct {
	api   *apitest.Client
	pool  *pgxpool.Pool
	clock *clock.Fake
}

func newFixture(t *testing.T) *fixture {
	t.Helper()

	f := &fixture{pool: testdb.New(t), clock: clock.NewFake(start)}

	app, err := bootstrap.New(bootstrap.Deps{
		Config: config.Config{
			AppURL: "http://localhost:8080", RateLimitPerMinute: 100_000, AppEnv: "testing", BcryptRounds: 4, JWT: testJWT,
			AppName: "My Workouts", FrontendURL: "https://app.example.test", LinkSigningKey: "test-link-signing-key",
		},
		Logger: slog.New(slog.DiscardHandler),
		Clock:  f.clock,
		Pool:   f.pool,
		Mailer: &mail.Fake{},
	})
	if err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { _ = app.Close(context.Background()) })

	f.api = apitest.New(t, app.Handler)
	f.exec(t, catalogSQL)

	return f
}

type user struct {
	ID    int64
	Token string
}

// signUp registers and verifies a user.
func (f *fixture) signUp(t *testing.T, email string) user {
	t.Helper()

	res := f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/register", Body: map[string]any{
		"name": "Ada", "email": email, "password": "secret123", "client": "native",
	}}).AssertStatus(http.StatusCreated)

	var body struct {
		AccessToken string `json:"access_token"`
		User        struct {
			ID int64 `json:"id"`
		} `json:"user"`
	}

	decode(t, res, &body)
	f.exec(t, "UPDATE users SET email_verified_at = now() WHERE id = $1", body.User.ID)

	return user{ID: body.User.ID, Token: body.AccessToken}
}

func (f *fixture) exec(t *testing.T, sql string, args ...any) {
	t.Helper()

	if _, err := f.pool.Exec(t.Context(), sql, args...); err != nil {
		t.Fatal(err)
	}
}

func (f *fixture) id(t *testing.T, sql string, args ...any) int64 {
	t.Helper()

	var id int64
	if err := f.pool.QueryRow(t.Context(), sql, args...).Scan(&id); err != nil {
		t.Fatal(err)
	}

	return id
}

// The PHP tests' factories: rows inserted directly.

func (f *fixture) workout(t *testing.T, userID int64, status string) int64 {
	t.Helper()

	return f.id(t, "INSERT INTO workouts (user_id, name, status, created_at, updated_at) VALUES ($1, 'Morning', $2, $3, $3) RETURNING id",
		userID, status, start)
}

func (f *fixture) activity(t *testing.T, workoutID, exerciseID int64, order int) int64 {
	t.Helper()

	return f.id(t, `INSERT INTO workout_activities (workout_id, exercise_id, "order") VALUES ($1, $2, $3) RETURNING id`,
		workoutID, exerciseID, order)
}

func (f *fixture) set(t *testing.T, activityID int64, order, effort int, difficulty any, completed bool) int64 {
	t.Helper()

	return f.id(t, `INSERT INTO workout_sets (activity_id, "order", effort_value, difficulty_value, is_completed)
		VALUES ($1, $2, $3, $4, $5) RETURNING id`, activityID, order, effort, difficulty, completed)
}

func (f *fixture) count(t *testing.T, sql string, args ...any) int {
	t.Helper()

	return int(f.id(t, sql, args...))
}

func (f *fixture) do(u user, method, path string, body any, header ...string) *apitest.Response {
	h := http.Header{}
	for i := 0; i+1 < len(header); i += 2 {
		h.Set(header[i], header[i+1])
	}

	return f.api.Do(apitest.Request{Method: method, Path: path, Token: u.Token, Body: body, Header: h})
}

// etag is the workout's current tag, read from the database.
func (f *fixture) etag(t *testing.T, workoutID int64) string {
	t.Helper()

	return fmt.Sprintf(`"%d-%d"`, workoutID, f.id(t, "SELECT version FROM workouts WHERE id = $1", workoutID))
}

// put replaces a workout's activities with its current ETag, as PHP's
// putActivities test helper does.
func (f *fixture) put(t *testing.T, u user, workoutID int64, body any) *apitest.Response {
	t.Helper()

	tag := `"0-0"`
	if f.count(t, "SELECT count(*) FROM workouts WHERE id = $1", workoutID) == 1 {
		tag = f.etag(t, workoutID)
	}

	return f.do(u, http.MethodPut, fmt.Sprintf("/api/v1/workouts/%d/activities", workoutID), body, "If-Match", tag)
}

func decode(t *testing.T, res *apitest.Response, v any) {
	t.Helper()

	if err := json.Unmarshal(res.Body.Bytes(), v); err != nil {
		t.Fatalf("decode %s: %v", res.Body, err)
	}
}

// workoutBody is a WorkoutResponse body.
type workoutBody struct {
	Data struct {
		ID              int64  `json:"id"`
		Name            string `json:"name"`
		Status          string `json:"status"`
		ActivitiesCount *int   `json:"activities_count"`
		WorkoutTemplate *struct {
			ID         int64             `json:"id"`
			Name       string            `json:"name"`
			Activities []json.RawMessage `json:"activities"`
		} `json:"workout_template"`
		Activities []struct {
			ID         int64 `json:"id"`
			ExerciseID int64 `json:"exercise_id"`
			Exercise   *struct {
				Name           string `json:"name"`
				EquipmentName  string `json:"equipment_name"`
				DifficultyUnit string `json:"difficulty_unit"`
			} `json:"exercise"`
			Sets []struct {
				ID              int64    `json:"id"`
				Order           int      `json:"order"`
				EffortValue     int      `json:"effort_value"`
				DifficultyValue *float64 `json:"difficulty_value"`
				IsCompleted     bool     `json:"is_completed"`
			} `json:"sets"`
		} `json:"activities"`
	} `json:"data"`
}

func readWorkout(t *testing.T, res *apitest.Response) workoutBody {
	t.Helper()

	var body workoutBody

	decode(t, res, &body)

	return body
}

// problem asserts a Problem's status and code.
func problem(t *testing.T, res *apitest.Response, status int, code string) {
	t.Helper()

	res.AssertStatus(status)

	if got := res.Problem().Code; string(got) != code {
		t.Errorf("code = %q, want %q", got, code)
	}
}

// fieldErrors asserts a 422 naming exactly these pointers (with code when
// given) and returns the errors by pointer.
func fieldErrors(t *testing.T, res *apitest.Response, code string, pointers ...string) map[string]string {
	t.Helper()

	res.AssertStatus(http.StatusUnprocessableEntity)

	got := map[string]string{}
	codes := map[string]string{}

	for _, e := range res.Problem().Errors {
		got[e.Pointer] = e.Detail
		codes[e.Pointer] = string(e.Code)
	}

	for _, p := range pointers {
		if _, ok := got[p]; !ok {
			t.Errorf("no error at %s; errors: %v", p, codes)
		}

		if code != "" && codes[p] != code {
			t.Errorf("%s: code %q, want %q", p, codes[p], code)
		}
	}

	if len(got) != len(pointers) {
		t.Errorf("errors at %v, want exactly %v", codes, pointers)
	}

	return got
}

// activity is one activity of a replace payload.
func activityPayload(id any, exerciseID int64, order int, sets ...map[string]any) map[string]any {
	a := map[string]any{"exercise_id": exerciseID, "order": order, "sets": append([]map[string]any{}, sets...)}
	if id != nil {
		a["id"] = id
	}

	return a
}

func payload(activities ...map[string]any) map[string]any {
	return map[string]any{"activities": append([]map[string]any{}, activities...)}
}

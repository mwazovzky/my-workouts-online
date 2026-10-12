package postgres_test

import (
	"context"
	"os"
	"slices"
	"sync/atomic"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"

	catalogpg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

// queryCounter counts the queries a pool runs.
type queryCounter struct{ n atomic.Int64 }

func (c *queryCounter) TraceQueryStart(ctx context.Context, _ *pgx.Conn, _ pgx.TraceQueryStartData) context.Context {
	c.n.Add(1)

	return ctx
}

func (*queryCounter) TraceQueryEnd(context.Context, *pgx.Conn, pgx.TraceQueryEndData) {}

// count returns how many queries fn ran.
func (c *queryCounter) count(fn func()) int64 {
	before := c.n.Load()
	fn()

	return c.n.Load() - before
}

type fixture struct {
	tx      *postgres.Transactor
	queries *queryCounter
}

// newFixture loads the HTTP tests' golden catalog (../http/testdata) into a
// fresh database, behind a pool that counts its queries.
func newFixture(t *testing.T) fixture {
	t.Helper()

	catalog, err := os.ReadFile("../http/testdata/catalog.sql")
	if err != nil {
		t.Fatal(err)
	}

	base := testdb.New(t)
	if _, err := base.Exec(t.Context(), string(catalog)); err != nil {
		t.Fatal(err)
	}

	counter := &queryCounter{}
	cfg := base.Config()
	cfg.ConnConfig.Tracer = counter

	pool, err := pgxpool.NewWithConfig(t.Context(), cfg)
	if err != nil {
		t.Fatal(err)
	}

	t.Cleanup(pool.Close)

	return fixture{tx: postgres.NewTransactor(pool), queries: counter}
}

var enrolledAt = time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC)

func ru(text string) map[i18n.Locale]string { return map[i18n.Locale]string{i18n.RU: text} }

func TestTemplateByIDReadsFiveQueriesWhateverItsSize(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	templates := catalogpg.NewTemplates(f.tx)

	for id, activities := range map[int64]int{1: 3, 2: 1} {
		var template domain.WorkoutTemplate

		n := f.queries.count(func() {
			var err error
			if template, err = templates.ByID(t.Context(), id); err != nil {
				t.Fatal(err)
			}
		})

		if n != 5 || len(template.Activities) != activities {
			t.Errorf("template %d: %d queries for %d activities, want 5 for %d", id, n, len(template.Activities), activities)
		}
	}
}

func TestTemplateByIDResolvesRetiredExercisesInOrder(t *testing.T) {
	t.Parallel()

	template, err := catalogpg.NewTemplates(newFixture(t).tx).ByID(t.Context(), 1)
	if err != nil {
		t.Fatal(err)
	}

	type activity struct {
		ID       int64
		Exercise string
		Retired  bool
		Sets     []int64
	}

	var got []activity

	for _, a := range template.Activities {
		sets := make([]int64, len(a.Sets))
		for i, s := range a.Sets {
			sets[i] = s.ID
		}

		got = append(got, activity{a.ID, a.Exercise.Name.Source, a.Exercise.IsRetired(), sets})
	}

	want := []activity{
		{ID: 2, Exercise: "Squat", Sets: []int64{3, 4}},
		{ID: 1, Exercise: "Push-up", Sets: []int64{2, 1}},
		{ID: 3, Exercise: "Upright Row", Retired: true, Sets: []int64{5}},
	}
	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("activities (-want +got):\n%s", diff)
	}

	if d := template.Activities[0].Sets[1].DifficultyValue; d == nil || *d != 102.5 {
		t.Errorf("squat set 2 difficulty = %v, want 102.5", d)
	}

	if d := template.Activities[1].Sets[0].DifficultyValue; d != nil {
		t.Errorf("push-up difficulty = %v, want nil (unit none)", *d)
	}

	if want := (domain.LocalizedText{Source: "Full Body A", Other: ru("Всё тело А")}); !cmp.Equal(template.Name, want) {
		t.Errorf("name = %+v, want %+v", template.Name, want)
	}
}

func TestTemplateByIDOfAnUnknownTemplateIsNotFound(t *testing.T) {
	t.Parallel()

	_, err := catalogpg.NewTemplates(newFixture(t).tx).ByID(t.Context(), 99)
	if !apperr.HasKind(err, apperr.KindNotFound) {
		t.Errorf("err = %v, want not found", err)
	}
}

func TestListActiveExercisesReadsTwoQueriesAndHidesRetired(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	var exercises []domain.Exercise

	n := f.queries.count(func() {
		var err error
		if exercises, err = catalogpg.NewExercises(f.tx).ListActive(t.Context()); err != nil {
			t.Fatal(err)
		}
	})

	if n != 2 {
		t.Errorf("%d queries, want 2", n)
	}

	var ids []int64
	for _, e := range exercises {
		ids = append(ids, e.ID)
	}

	if want := []int64{1, 2, 3, 5, 6, 7}; !slices.Equal(ids, want) {
		t.Errorf("ids = %v, want %v", ids, want)
	}

	deadlift := exercises[4]
	want := domain.Exercise{
		ID: 6, Name: domain.LocalizedText{Source: "Deadlift", Other: ru("Становая тяга")},
		Equipment:  domain.Equipment{ID: 1, Name: domain.LocalizedText{Source: "Barbell", Other: ru("Штанга")}, DifficultyUnit: domain.Kilograms},
		EffortType: domain.Repetitions, RestTimeSeconds: new(180),
		Categories: []domain.Category{
			{ID: 2, Name: domain.LocalizedText{Source: "Legs", Other: ru("Ноги")}},
			{ID: 3, Name: domain.LocalizedText{Source: "Back"}},
		},
	}
	if diff := cmp.Diff(want, deadlift); diff != "" {
		t.Errorf("deadlift (-want +got):\n%s", diff)
	}

	if plank := exercises[3]; plank.RestTimeSeconds != nil || len(plank.Categories) != 0 || plank.Categories == nil {
		t.Errorf("plank: rest %v, categories %#v; want nil rest and an empty list", plank.RestTimeSeconds, plank.Categories)
	}
}

func TestExercisesByIDsIncludesRetiredAndSkipsUnknown(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	exercises := catalogpg.NewExercises(f.tx)

	got, err := exercises.ByIDs(t.Context(), []int64{4, 99})
	if err != nil {
		t.Fatal(err)
	}

	if len(got) != 1 || got[0].ID != 4 || !got[0].IsRetired() {
		t.Errorf("ByIDs(4, 99) = %+v, want the retired exercise 4 only", got)
	}

	if n := f.queries.count(func() {
		if got, err := exercises.ByIDs(t.Context(), nil); err != nil || len(got) != 0 {
			t.Errorf("ByIDs(nil) = %v, %v", got, err)
		}
	}); n != 0 {
		t.Errorf("ByIDs(nil) ran %d queries", n)
	}
}

func TestProgramByIDKeepsTheAssignmentOrder(t *testing.T) {
	t.Parallel()

	f := newFixture(t)

	entry, err := catalogpg.NewPrograms(f.tx).ByID(t.Context(), 1, 1)
	if err != nil {
		t.Fatal(err)
	}

	type assignment struct {
		Template int64
		Weekday  domain.Weekday
	}

	var got []assignment
	for _, a := range entry.Program.Assignments {
		got = append(got, assignment{a.Template.ID, a.Weekday})
	}

	want := []assignment{{1, domain.Thursday}, {2, domain.Wednesday}, {1, domain.Monday}}
	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("assignments (-want +got):\n%s", diff)
	}

	if entry.Enrolled {
		t.Error("user 1 does not exist, so is not enrolled")
	}

	if want := (domain.LocalizedText{Source: "Three sessions a week.", Other: ru("Три тренировки в неделю.")}); !cmp.Equal(entry.Program.Description, want) {
		t.Errorf("description = %+v, want %+v", entry.Program.Description, want)
	}

	if _, err := catalogpg.NewPrograms(f.tx).ByID(t.Context(), 99, 1); !apperr.HasKind(err, apperr.KindNotFound) {
		t.Errorf("unknown program: err = %v, want not found", err)
	}
}

func TestEnrolledScheduleAndEnrollment(t *testing.T) {
	t.Parallel()

	f := newFixture(t)
	ctx := t.Context()
	programs := catalogpg.NewPrograms(f.tx)

	var userID int64
	if err := f.tx.Querier(ctx).QueryRow(ctx,
		"INSERT INTO users (name, email, password_hash) VALUES ('Ada', 'ada@example.com', 'x') RETURNING id").Scan(&userID); err != nil {
		t.Fatal(err)
	}

	for _, programID := range []int64{1, 2, 1} {
		if err := programs.Enroll(ctx, programID, userID, enrolledAt); err != nil {
			t.Fatal(err)
		}
	}

	schedule, err := programs.EnrolledSchedule(ctx, userID)
	if err != nil {
		t.Fatal(err)
	}

	var weekdays []domain.Weekday
	for _, s := range schedule {
		weekdays = append(weekdays, s.Weekday)
	}

	// Program 2 schedules nothing; program 1's assignments keep their order.
	if want := []domain.Weekday{domain.Thursday, domain.Wednesday, domain.Monday}; !slices.Equal(weekdays, want) {
		t.Errorf("weekdays = %v, want %v", weekdays, want)
	}

	if s := schedule[1]; s.ProgramName.Resolve(i18n.RU) != "Сила" || s.TemplateName.Resolve(i18n.RU) != "Core" {
		t.Errorf("schedule[1] = %+v", s)
	}

	list, err := programs.List(ctx, userID)
	if err != nil {
		t.Fatal(err)
	}

	if got := []bool{list[0].Enrolled, list[1].Enrolled}; !slices.Equal(got, []bool{true, true}) {
		t.Errorf("enrolled = %v", got)
	}

	if err := programs.Unenroll(ctx, 2, userID); err != nil {
		t.Fatal(err)
	}

	for id, want := range map[int64]bool{1: true, 2: true, 99: false} {
		if exists, err := programs.Exists(ctx, id); err != nil || exists != want {
			t.Errorf("Exists(%d) = %v, %v; want %v", id, exists, err, want)
		}
	}

	if entry, err := programs.ByID(ctx, 2, userID); err != nil || entry.Enrolled {
		t.Errorf("program 2 after unenrolling: %+v, %v", entry, err)
	}
}

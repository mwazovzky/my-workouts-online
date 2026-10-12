package app_test

import (
	"slices"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/dashboard/app"
)

var names = []string{"sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"}

// Every today × target pair: the date falls on the target within the next
// seven days, today included, exactly (target − today + 7) % 7 days ahead.
func TestNextDateForEveryPairOfWeekdays(t *testing.T) {
	t.Parallel()

	sunday := time.Date(2026, 10, 11, 0, 0, 0, 0, time.UTC)

	for d := range 7 {
		today := sunday.AddDate(0, 0, d)

		for target, name := range names {
			got := app.NextDate(name, today)
			days := int(got.Sub(today).Hours() / 24)

			if got.Weekday() != time.Weekday(target) || days != (target-d+7)%7 || got.Location() != time.UTC {
				t.Errorf("%s from %s = %s (%d days)", name, today.Weekday(), got, days)
			}
		}
	}
}

// Mirrors DashboardApiTest::index_includes_upcoming_schedule_for_all_weekdays
// (frozen on Monday 2025-01-06): Monday is today, Sunday six days ahead.
func TestNextDateFromAMonday(t *testing.T) {
	t.Parallel()

	monday := app.Today(time.Date(2025, 1, 6, 10, 0, 0, 0, time.UTC))

	want := []string{"2025-01-12", "2025-01-06", "2025-01-07", "2025-01-08", "2025-01-09", "2025-01-10", "2025-01-11"}
	for i, name := range names {
		if got := app.NextDate(name, monday).Format(time.DateOnly); got != want[i] {
			t.Errorf("%s = %s, want %s", name, got, want[i])
		}
	}

	// The story's example: from a Wednesday, Monday is five days ahead and
	// Wednesday is today.
	wednesday := app.Today(time.Date(2026, 10, 14, 18, 0, 0, 0, time.UTC))
	if got := app.NextDate("monday", wednesday).Format(time.DateOnly); got != "2026-10-19" {
		t.Errorf("monday from a Wednesday = %s", got)
	}

	if got := app.NextDate("wednesday", wednesday); !got.Equal(wednesday) {
		t.Errorf("wednesday from a Wednesday = %s", got)
	}
}

// The day is the UTC day, whatever the clock's zone, so a DST change
// shifts nothing: 00:30 on the night Berlin moves its clocks forward is
// still the previous day in UTC.
func TestTodayIsTheUTCDay(t *testing.T) {
	t.Parallel()

	berlin, err := time.LoadLocation("Europe/Berlin")
	if err != nil {
		t.Skip("no tz database:", err)
	}

	tests := map[time.Time]string{
		time.Date(2026, 3, 29, 0, 30, 0, 0, berlin):  "2026-03-28",
		time.Date(2026, 3, 29, 3, 30, 0, 0, berlin):  "2026-03-29",
		time.Date(2026, 10, 25, 1, 30, 0, 0, berlin): "2026-10-24",
		time.Date(2026, 10, 25, 2, 30, 0, 0, berlin): "2026-10-25",
	}

	for now, want := range tests {
		today := app.Today(now)
		if today.Format(time.DateOnly) != want || today.Location() != time.UTC || today.Hour() != 0 {
			t.Errorf("Today(%s) = %s, want %s UTC midnight", now, today, want)
		}

		if days := app.NextDate("sunday", today).Sub(today) / (24 * time.Hour); days < 0 || days > 6 {
			t.Errorf("a week from %s spans %d days", today, days)
		}
	}
}

// Upcoming ids are "{program}-{template}-{date}", sorted by date with
// same-day items in assignment order.
func TestUpcomingIDsAndOrder(t *testing.T) {
	t.Parallel()

	monday := app.Today(time.Date(2025, 1, 6, 10, 0, 0, 0, time.UTC))

	got := app.Upcoming([]app.Assignment{
		{ProgramID: 1, TemplateID: 10, Weekday: "wednesday"},
		{ProgramID: 2, TemplateID: 11, Weekday: "monday"},
		{ProgramID: 1, TemplateID: 11, Weekday: "monday"},
		{ProgramID: 1, TemplateID: 12, Weekday: "sunday"},
	}, monday)

	var ids []string
	for _, s := range got {
		ids = append(ids, s.ID)
	}

	want := []string{"2-11-2025-01-06", "1-11-2025-01-06", "1-10-2025-01-08", "1-12-2025-01-12"}
	if !slices.Equal(ids, want) {
		t.Errorf("ids = %v, want %v", ids, want)
	}

	if len(app.Upcoming(nil, monday)) != 0 {
		t.Error("no assignments, yet a schedule")
	}
}

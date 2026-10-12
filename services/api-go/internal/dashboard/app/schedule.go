package app

import (
	"fmt"
	"slices"
	"time"
)

// weekdays maps the contract's weekday names to Go's (Sunday is 0, as in
// Carbon's dayOfWeek).
var weekdays = map[string]time.Weekday{
	"sunday": time.Sunday, "monday": time.Monday, "tuesday": time.Tuesday, "wednesday": time.Wednesday,
	"thursday": time.Thursday, "friday": time.Friday, "saturday": time.Saturday,
}

// Today is the start of now's day in UTC: the PHP API runs in UTC, so a
// day boundary is UTC midnight wherever the user is.
func Today(now time.Time) time.Time {
	now = now.UTC()

	return time.Date(now.Year(), now.Month(), now.Day(), 0, 0, 0, 0, time.UTC)
}

// NextDate is the first day on or after today that falls on weekday: today
// itself when it matches. This is PHP's nextScheduledDate,
// (target − today.dayOfWeek + 7) % 7 days ahead.
func NextDate(weekday string, today time.Time) time.Time {
	days := (int(weekdays[weekday]) - int(today.Weekday()) + 7) % 7

	return today.AddDate(0, 0, days)
}

// Scheduled is one upcoming workout: an assignment's next date.
type Scheduled struct {
	// ID is "{program_id}-{workout_template_id}-{YYYY-MM-DD}", which the
	// clients key on.
	ID           string
	ProgramID    int64
	ProgramName  string
	TemplateID   int64
	TemplateName string
	Weekday      string
	// Date is the UTC midnight of the scheduled day.
	Date time.Time
}

// Assignment is a template a program schedules on a weekday.
type Assignment struct {
	ProgramID    int64
	ProgramName  string
	TemplateID   int64
	TemplateName string
	Weekday      string
}

// Upcoming dates every assignment within the week from today, sorted by
// date; assignments on the same day keep their order (a stable sort, as
// PHP's sortBy).
func Upcoming(assignments []Assignment, today time.Time) []Scheduled {
	schedule := make([]Scheduled, len(assignments))

	for i, a := range assignments {
		date := NextDate(a.Weekday, today)
		schedule[i] = Scheduled{
			ID:        fmt.Sprintf("%d-%d-%s", a.ProgramID, a.TemplateID, date.Format(time.DateOnly)),
			ProgramID: a.ProgramID, ProgramName: a.ProgramName,
			TemplateID: a.TemplateID, TemplateName: a.TemplateName,
			Weekday: a.Weekday, Date: date,
		}
	}

	slices.SortStableFunc(schedule, func(a, b Scheduled) int { return a.Date.Compare(b.Date) })

	return schedule
}

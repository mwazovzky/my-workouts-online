// Package http implements the dashboard's contract operation, getDashboard.
// Workouts are shown as in the history list: no activities, their count and
// their template's header; in_progress_workout is left out, not null, when
// there is none.
package http

import (
	"context"
	"slices"

	openapi_types "github.com/oapi-codegen/runtime/types"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/dashboard/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// Dashboard implements the dashboard's operation.
type Dashboard struct {
	service *app.Service
}

// New returns the dashboard's handler.
func New(service *app.Service) Dashboard {
	return Dashboard{service: service}
}

// GetDashboard implements getDashboard.
func (h Dashboard) GetDashboard(ctx context.Context, _ gen.GetDashboardRequestObject) (gen.GetDashboardResponseObject, error) {
	p, ok := auth.FromContext(ctx)
	if !ok {
		return nil, apperr.Unauthenticated(apperr.CodeUnauthenticated, "Unauthenticated.")
	}

	d, err := h.service.Get(ctx, p.UserID, i18n.FromContext(ctx))
	if err != nil {
		return nil, err
	}

	var out gen.Dashboard

	// The item type is anonymous in the generated code; grow the slice to
	// get zero items without spelling it out (at least capacity 1, so an
	// empty schedule is [] and not null).
	out.UpcomingSchedule = slices.Grow(out.UpcomingSchedule, max(len(d.Upcoming), 1))[:len(d.Upcoming)]

	for i, s := range d.Upcoming {
		item := &out.UpcomingSchedule[i]
		item.Id, item.ProgramId, item.ProgramName = s.ID, int(s.ProgramID), text(s.ProgramName)
		item.WorkoutTemplateId, item.WorkoutName, item.Weekday = int(s.TemplateID), text(s.TemplateName), gen.Weekday(s.Weekday)
		item.ScheduledAt, item.ScheduledFor = s.Date, openapi_types.Date{Time: s.Date}
	}

	if d.InProgress != nil {
		w := workout(*d.InProgress, d.Templates)
		out.InProgressWorkout = &w
	}

	out.RecentWorkouts = make([]gen.Workout, len(d.Recent))
	for i, w := range d.Recent {
		out.RecentWorkouts[i] = workout(w, d.Templates)
	}

	out.Summary.EnrolledProgramsCount = d.Summary.EnrolledPrograms
	out.Summary.CompletedWorkoutsCount = d.Summary.CompletedWorkouts
	out.Summary.CompletedLast7DaysCount = d.Summary.CompletedLast7Days
	out.Summary.UpcomingWorkoutsCount = d.Summary.UpcomingWorkouts

	return gen.GetDashboard200JSONResponse{Data: out}, nil
}

// workout is a workout as the history list shows it.
func workout(w app.Workout, templates map[int64]app.TemplateHeader) gen.Workout {
	count := w.ActivitiesCount
	out := gen.Workout{
		Id: int(w.ID), UserId: int(w.UserID), Name: w.Name, Status: gen.WorkoutStatus(w.Status),
		CreatedAt: w.CreatedAt, UpdatedAt: w.UpdatedAt, ActivitiesCount: &count, Activities: []gen.Activity{},
	}

	if w.TemplateID != nil {
		if t, ok := templates[*w.TemplateID]; ok {
			out.WorkoutTemplate = &gen.WorkoutTemplate{
				Id: int(t.ID), Name: text(t.Name), Description: text(t.Description), Activities: []gen.Activity{},
			}
		}
	}

	return out
}

// text is a localized text, null when empty.
func text(s string) *string {
	if s == "" {
		return nil
	}

	return &s
}

// Package http implements the training module's contract operations on the
// generated strict server: listWorkouts, createWorkout, getWorkout,
// replaceWorkoutActivities, completeWorkout, repeatWorkout and deleteWorkout.
// Every single-workout response carries the workout's ETag. Each operation
// shows what PHP's response shows: only getWorkout and the replace include
// the activities with their exercises; createWorkout embeds the template it
// copied; the list and getWorkout the template's header.
package http

import (
	"context"
	"net/url"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/pagination"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/domain"
)

// FieldMessages are the wording of PHP's WorkoutStoreRequest and
// ReplaceWorkoutActivitiesRequest, for the request validator.
func FieldMessages() map[string]httpapi.FieldMessages {
	return map[string]httpapi.FieldMessages{
		"createWorkout": {
			Attributes: map[string]string{"/workout_template_id": "workout template"},
			Details:    map[string]string{"/workout_template_id required": "A workout template is required to start a workout."},
		},
		"replaceWorkoutActivities": {
			Attributes: map[string]string{
				"/activities":                           "activities",
				"/activities/*/exercise_id":             "exercise",
				"/activities/*/order":                   "activity order",
				"/activities/*/sets":                    "sets",
				"/activities/*/sets/*/order":            "set order",
				"/activities/*/sets/*/effort_value":     "effort value",
				"/activities/*/sets/*/difficulty_value": "difficulty value",
				"/activities/*/sets/*/is_completed":     "completion status",
			},
		},
	}
}

// Training implements the training module's operations.
type Training struct {
	service *app.Service
}

// New returns the training module's handlers.
func New(service *app.Service) Training {
	return Training{service: service}
}

// caller is the authenticated principal; the authorize middleware lets no
// request reach these operations without one.
func caller(ctx context.Context) (auth.Principal, error) {
	p, ok := auth.FromContext(ctx)
	if !ok {
		return auth.Principal{}, apperr.Unauthenticated(apperr.CodeUnauthenticated, "Unauthenticated.")
	}

	return p, nil
}

// ListWorkouts implements listWorkouts.
func (h Training) ListWorkouts(ctx context.Context, req gen.ListWorkoutsRequestObject) (gen.ListWorkoutsResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	locale := i18n.FromContext(ctx)
	page := pagination.NewPage(req.Params.Page, req.Params.PerPage, app.DefaultPageSize, app.MaxPageSize)

	result, templates, err := h.service.List(ctx, p, page, locale)
	if err != nil {
		return nil, err
	}

	data := make([]gen.Workout, len(result.Items))
	for i, w := range result.Items {
		data[i] = workout(app.View{Workout: w, Template: templateOf(w, templates)})
	}

	meta, links := result.Meta(), result.Links(requestURL(ctx))

	return gen.ListWorkouts200JSONResponse{
		Data:  data,
		Links: gen.PageLinks{First: links.First, Last: links.Last, Prev: links.Prev, Next: links.Next},
		Meta: gen.PageMeta{
			CurrentPage: meta.CurrentPage, From: meta.From, LastPage: meta.LastPage, PerPage: meta.PerPage, To: meta.To, Total: meta.Total,
		},
	}, nil
}

func templateOf(w domain.Workout, templates map[int64]app.TemplateHeader) *app.TemplateHeader {
	if w.TemplateID == nil {
		return nil
	}

	header, ok := templates[*w.TemplateID]
	if !ok {
		return nil
	}

	return &header
}

// requestURL is the absolute URL of the request, as Laravel's paginator
// builds its links: the scheme and host the request came in on.
func requestURL(ctx context.Context) *url.URL {
	r := httpapi.Request(ctx)

	u := *r.URL
	u.Scheme, u.Host = "http", r.Host

	if r.TLS != nil {
		u.Scheme = "https"
	}

	return &u
}

// CreateWorkout implements createWorkout.
func (h Training) CreateWorkout(ctx context.Context, req gen.CreateWorkoutRequestObject) (gen.CreateWorkoutResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	view, err := h.service.Create(ctx, p, int64(req.Body.WorkoutTemplateId), i18n.FromContext(ctx))
	if err != nil {
		return nil, err
	}

	return gen.CreateWorkout201JSONResponse{WorkoutResponseJSONResponse: response(view)}, nil
}

// GetWorkout implements getWorkout.
func (h Training) GetWorkout(ctx context.Context, req gen.GetWorkoutRequestObject) (gen.GetWorkoutResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	view, err := h.service.Get(ctx, p, int64(req.WorkoutId), i18n.FromContext(ctx))
	if err != nil {
		return nil, err
	}

	return gen.GetWorkout200JSONResponse{WorkoutResponseJSONResponse: response(view)}, nil
}

// ReplaceWorkoutActivities implements replaceWorkoutActivities.
func (h Training) ReplaceWorkoutActivities(ctx context.Context, req gen.ReplaceWorkoutActivitiesRequestObject) (gen.ReplaceWorkoutActivitiesResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	in := make([]app.ActivityInput, len(req.Body.Activities))

	for i, a := range req.Body.Activities {
		sets := make([]app.SetInput, len(a.Sets))
		for j, s := range a.Sets {
			sets[j] = app.SetInput{
				ID: id64(s.Id), Order: s.Order, EffortValue: s.EffortValue, DifficultyValue: float64Of(s.DifficultyValue),
				IsCompleted: s.IsCompleted != nil && *s.IsCompleted,
			}
		}

		in[i] = app.ActivityInput{ID: id64(a.Id), ExerciseID: int64(a.ExerciseId), Order: a.Order, Sets: sets}
	}

	view, err := h.service.ReplaceActivities(ctx, p, int64(req.WorkoutId), req.Params.IfMatch, in, i18n.FromContext(ctx))
	if err != nil {
		return nil, err
	}

	return gen.ReplaceWorkoutActivities200JSONResponse{WorkoutResponseJSONResponse: response(view)}, nil
}

// CompleteWorkout implements completeWorkout.
func (h Training) CompleteWorkout(ctx context.Context, req gen.CompleteWorkoutRequestObject) (gen.CompleteWorkoutResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	view, err := h.service.Complete(ctx, p, int64(req.WorkoutId))
	if err != nil {
		return nil, err
	}

	return gen.CompleteWorkout200JSONResponse{WorkoutResponseJSONResponse: response(view)}, nil
}

// RepeatWorkout implements repeatWorkout.
func (h Training) RepeatWorkout(ctx context.Context, req gen.RepeatWorkoutRequestObject) (gen.RepeatWorkoutResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	view, err := h.service.Repeat(ctx, p, int64(req.WorkoutId))
	if err != nil {
		return nil, err
	}

	return gen.RepeatWorkout201JSONResponse{WorkoutResponseJSONResponse: response(view)}, nil
}

// DeleteWorkout implements deleteWorkout.
func (h Training) DeleteWorkout(ctx context.Context, req gen.DeleteWorkoutRequestObject) (gen.DeleteWorkoutResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	if err := h.service.Delete(ctx, p, int64(req.WorkoutId)); err != nil {
		return nil, err
	}

	return gen.DeleteWorkout204Response{}, nil
}

func response(view app.View) gen.WorkoutResponseJSONResponse {
	r := gen.WorkoutResponseJSONResponse{Headers: gen.WorkoutResponseResponseHeaders{ETag: view.Workout.ETag()}}
	r.Body.Data = workout(view)

	return r
}

// workout is the contract's Workout for a view: activities only when they
// were loaded, the template header or the snapshot when there is one.
func workout(view app.View) gen.Workout {
	w := view.Workout

	out := gen.Workout{
		Id: int(w.ID), UserId: int(w.UserID), Name: w.Name, Status: gen.WorkoutStatus(w.Status),
		CreatedAt: w.CreatedAt, UpdatedAt: w.UpdatedAt, ActivitiesCount: w.ActivitiesCount,
		Activities: make([]gen.Activity, len(w.Activities)),
	}

	for i, a := range w.Activities {
		sets := make([]gen.Set, len(a.Sets))
		for j, s := range a.Sets {
			sets[j] = gen.Set{Id: int(s.ID), Order: s.Order, EffortValue: s.EffortValue, DifficultyValue: float32Of(s.DifficultyValue), IsCompleted: s.IsCompleted}
		}

		out.Activities[i] = gen.Activity{Id: int(a.ID), ExerciseId: int(a.ExerciseID), Exercise: exercise(view.Exercises, a.ExerciseID), Sets: sets}
	}

	switch {
	case view.Snapshot != nil:
		out.WorkoutTemplate = snapshot(*view.Snapshot)
	case view.Template != nil:
		out.WorkoutTemplate = &gen.WorkoutTemplate{
			Id: int(view.Template.ID), Name: text(view.Template.Name), Description: text(view.Template.Description), Activities: []gen.Activity{},
		}
	}

	return out
}

// snapshot is the template a workout was created from, with its activities
// and sets but not their exercises, as PHP's create response shows it.
func snapshot(s app.Snapshot) *gen.WorkoutTemplate {
	t := &gen.WorkoutTemplate{Id: int(s.ID), Name: text(s.Name), Description: text(s.Description), Activities: make([]gen.Activity, len(s.Activities))}

	for i, a := range s.Activities {
		sets := make([]gen.Set, len(a.Sets))
		for j, set := range a.Sets {
			sets[j] = gen.Set{Id: int(set.ID), Order: set.Order, EffortValue: set.EffortValue, DifficultyValue: float32Of(set.DifficultyValue)}
		}

		t.Activities[i] = gen.Activity{Id: int(a.ID), ExerciseId: int(a.ExerciseID), Sets: sets}
	}

	return t
}

// exercise is an activity's exercise snapshot, when the exercise was looked
// up.
func exercise(exercises map[int64]app.ExerciseInfo, id int64) *gen.ActivityExercise {
	e, ok := exercises[id]
	if !ok {
		return nil
	}

	return &gen.ActivityExercise{
		Id: int(e.ID), Name: text(e.Name), Description: text(e.Description), EquipmentName: text(e.EquipmentName), EffortType: gen.EffortType(e.EffortType),
		DifficultyUnit: gen.DifficultyUnit(e.DifficultyUnit), CategoryNames: e.CategoryNames, RestTimeSeconds: e.RestTimeSeconds,
	}
}

// text is a localized text, null when empty.
func text(s string) *string {
	if s == "" {
		return nil
	}

	return &s
}

func id64(id *int) *int64 {
	if id == nil {
		return nil
	}

	v := int64(*id)

	return &v
}

func float64Of(v *float32) *float64 {
	if v == nil {
		return nil
	}

	f := float64(*v)

	return &f
}

func float32Of(v *float64) *float32 {
	if v == nil {
		return nil
	}

	f := float32(*v)

	return &f
}

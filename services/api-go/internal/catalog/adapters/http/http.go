// Package http implements the catalog's contract operations on the
// generated strict server: listPrograms, getProgram, enrollProgram,
// unenrollProgram, getWorkoutTemplate and listExercises. Text is resolved in
// the request's locale; enums are sent as their contract values and clients
// render the labels.
package http

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app/admin"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// Catalog implements the catalog's operations. Each module's handler type
// is named after the module, so the embedded fields of bootstrap's api
// struct do not collide.
type Catalog struct {
	service *app.Service
	admin   *admin.Service
}

// New returns the catalog's handlers.
func New(service *app.Service, adminService *admin.Service) Catalog {
	return Catalog{service: service, admin: adminService}
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

// ListPrograms implements listPrograms.
func (h Catalog) ListPrograms(ctx context.Context, _ gen.ListProgramsRequestObject) (gen.ListProgramsResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	entries, err := h.service.ListPrograms(ctx, p)
	if err != nil {
		return nil, err
	}

	locale := i18n.FromContext(ctx)
	data := make([]gen.Program, len(entries))

	for i, e := range entries {
		data[i] = gen.Program{
			Id: int(e.Program.ID), Name: text(e.Program.Name, locale), Description: text(e.Program.Description, locale),
			IsEnrolled: e.Enrolled,
		}
	}

	return gen.ListPrograms200JSONResponse{Data: data}, nil
}

// GetProgram implements getProgram.
func (h Catalog) GetProgram(ctx context.Context, req gen.GetProgramRequestObject) (gen.GetProgramResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	entry, err := h.service.GetProgram(ctx, p, int64(req.ProgramId))
	if err != nil {
		return nil, err
	}

	locale := i18n.FromContext(ctx)
	program := entry.Program
	templates := make([]gen.WorkoutTemplate, len(program.Assignments))

	for i, a := range program.Assignments {
		templates[i] = workoutTemplate(a.Template, locale)
		templates[i].Assignment = &struct {
			Weekday gen.Weekday `json:"weekday"`
		}{Weekday: gen.Weekday(a.Weekday)}
	}

	return gen.GetProgram200JSONResponse{Data: gen.ProgramDetail{
		Id: int(program.ID), Name: text(program.Name, locale), Description: text(program.Description, locale),
		IsEnrolled: entry.Enrolled, WorkoutTemplates: templates,
	}}, nil
}

// EnrollProgram implements enrollProgram.
func (h Catalog) EnrollProgram(ctx context.Context, req gen.EnrollProgramRequestObject) (gen.EnrollProgramResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	if err := h.service.Enroll(ctx, p, int64(req.ProgramId)); err != nil {
		return nil, err
	}

	return gen.EnrollProgram204Response{}, nil
}

// UnenrollProgram implements unenrollProgram.
func (h Catalog) UnenrollProgram(ctx context.Context, req gen.UnenrollProgramRequestObject) (gen.UnenrollProgramResponseObject, error) {
	p, err := caller(ctx)
	if err != nil {
		return nil, err
	}

	if err := h.service.Unenroll(ctx, p, int64(req.ProgramId)); err != nil {
		return nil, err
	}

	return gen.UnenrollProgram204Response{}, nil
}

// GetWorkoutTemplate implements getWorkoutTemplate.
func (h Catalog) GetWorkoutTemplate(ctx context.Context, req gen.GetWorkoutTemplateRequestObject) (gen.GetWorkoutTemplateResponseObject, error) {
	template, err := h.service.GetWorkoutTemplate(ctx, int64(req.WorkoutTemplateId))
	if err != nil {
		return nil, err
	}

	return gen.GetWorkoutTemplate200JSONResponse{Data: workoutTemplate(template, i18n.FromContext(ctx))}, nil
}

// ListExercises implements listExercises.
func (h Catalog) ListExercises(ctx context.Context, _ gen.ListExercisesRequestObject) (gen.ListExercisesResponseObject, error) {
	locale := i18n.FromContext(ctx)

	exercises, err := h.service.ListExercises(ctx, locale)
	if err != nil {
		return nil, err
	}

	data := make([]gen.Exercise, len(exercises))
	for i, e := range exercises {
		data[i] = exercise(e, locale)
	}

	return gen.ListExercises200JSONResponse{Data: data}, nil
}

// workoutTemplate is a template with whatever activities were loaded (none
// inside a program).
func workoutTemplate(t domain.WorkoutTemplate, locale i18n.Locale) gen.WorkoutTemplate {
	activities := make([]gen.Activity, len(t.Activities))

	for i, a := range t.Activities {
		sets := make([]gen.Set, len(a.Sets))
		for j, s := range a.Sets {
			sets[j] = gen.Set{
				Id: int(s.ID), Order: s.Order, EffortValue: s.EffortValue, DifficultyValue: difficulty(s.DifficultyValue),
				IsCompleted: false,
			}
		}

		activities[i] = gen.Activity{
			Id: int(a.ID), ExerciseId: int(a.Exercise.ID), Exercise: activityExercise(a.Exercise, locale), Sets: sets,
		}
	}

	return gen.WorkoutTemplate{
		Id: int(t.ID), Name: text(t.Name, locale), Description: text(t.Description, locale), Activities: activities,
	}
}

// activityExercise is the exercise snapshot an activity carries; retired
// exercises resolve like any other.
func activityExercise(e domain.Exercise, locale i18n.Locale) *gen.ActivityExercise {
	categories := make([]string, len(e.Categories))
	for i, c := range e.Categories {
		categories[i] = c.Name.Resolve(locale)
	}

	return &gen.ActivityExercise{
		Id:              int(e.ID),
		Name:            text(e.Name, locale),
		EquipmentName:   text(e.Equipment.Name, locale),
		EffortType:      gen.EffortType(e.EffortType),
		DifficultyUnit:  gen.DifficultyUnit(e.Equipment.DifficultyUnit),
		CategoryNames:   categories,
		RestTimeSeconds: e.RestTimeSeconds,
	}
}

func exercise(e domain.Exercise, locale i18n.Locale) gen.Exercise {
	ids := make([]int, len(e.Categories))
	categories := make([]gen.Category, len(e.Categories))

	for i, c := range e.Categories {
		ids[i] = int(c.ID)
		categories[i] = gen.Category{Id: int(c.ID), Name: text(c.Name, locale)}
	}

	unit := gen.DifficultyUnit(e.Equipment.DifficultyUnit)

	return gen.Exercise{
		Id:              int(e.ID),
		Name:            text(e.Name, locale),
		Description:     text(e.Description, locale),
		EquipmentId:     int(e.Equipment.ID),
		EquipmentName:   text(e.Equipment.Name, locale),
		EffortType:      gen.EffortType(e.EffortType),
		DifficultyUnit:  &unit,
		RestTimeSeconds: e.RestTimeSeconds,
		CategoryIds:     &ids,
		Categories:      &categories,
	}
}

// text is t in locale, or null when it has no text (PHP stores a blank
// source as null).
func text(t domain.LocalizedText, locale i18n.Locale) *string {
	s := t.Resolve(locale)
	if s == "" {
		return nil
	}

	return &s
}

func difficulty(v *float64) *float32 {
	if v == nil {
		return nil
	}

	f := float32(*v)

	return &f
}

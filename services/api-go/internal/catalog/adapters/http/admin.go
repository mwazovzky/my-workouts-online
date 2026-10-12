package http

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app/admin"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// The admin operations show what PHP's Admin*Resource classes show for each
// endpoint: a list carries the counts and no nested rows, a single record
// its nested rows and no counts, and an exercise's create and update
// responses leave out its equipment, which PHP does not reload.

// locales are the translation maps' keys.
var locales = []i18n.Locale{i18n.EN, i18n.RU}

// adminWrites are the ten create and update operations.
var adminWrites = []string{
	"adminCreateEquipment", "adminUpdateEquipment", "adminCreateCategory", "adminUpdateCategory",
	"adminCreateExercise", "adminUpdateExercise", "adminCreateWorkoutTemplate", "adminUpdateWorkoutTemplate",
	"adminCreateProgram", "adminUpdateProgram",
}

// FieldMessages are the wording of PHP's admin form requests
// (HasTranslationRules), for the request validator.
func FieldMessages() map[string]httpapi.FieldMessages {
	out := map[string]httpapi.FieldMessages{}

	for _, op := range adminWrites {
		out[op] = httpapi.FieldMessages{
			Attributes: map[string]string{
				"/translations/en/name": "translations.en.name", "/translations/ru/name": "translations.ru.name",
				"/translations/en/description": "translations.en.description", "/translations/ru/description": "translations.ru.description",
			},
			Details: map[string]string{"/translations/en/name required": "An English name is required."},
		}
	}

	return out
}

// translationMap is a field's stored value per locale: English from the
// column, the rest from translations, null where there is none.
func translationMap(t domain.LocalizedText) *gen.TranslationMap {
	m := gen.TranslationMap{}

	for _, locale := range locales {
		value := t.Other[locale]
		if locale == i18n.EN {
			value = t.Source
		}

		m[string(locale)] = text(domain.LocalizedText{Source: value}, locale)
	}

	return &m
}

func nameTranslations(in gen.NameTranslationsWrite) admin.Translations {
	t := admin.Translations{EN: admin.Text{Name: &in.En.Name}}
	if in.Ru != nil {
		t.Other = map[i18n.Locale]admin.Text{i18n.RU: {Name: in.Ru.Name}}
	}

	return t
}

func translations(in gen.TranslationsWrite) admin.Translations {
	t := admin.Translations{EN: admin.Text{Name: &in.En.Name, Description: in.En.Description}}
	if in.Ru != nil {
		t.Other = map[i18n.Locale]admin.Text{i18n.RU: {Name: in.Ru.Name, Description: in.Ru.Description}}
	}

	return t
}

// --- Equipment ---------------------------------------------------------

func equipment(e admin.Equipment, locale i18n.Locale) gen.Equipment {
	out := gen.Equipment{
		Id: int(e.ID), Name: text(e.Name, locale), DifficultyUnit: gen.DifficultyUnit(e.DifficultyUnit), ExercisesCount: e.ExercisesCount,
	}
	out.Translations = &struct {
		Name *gen.TranslationMap `json:"name,omitempty"`
	}{Name: translationMap(e.Name)}

	return out
}

func equipmentResponse(e admin.Equipment, locale i18n.Locale) gen.EquipmentResponseJSONResponse {
	return gen.EquipmentResponseJSONResponse{Data: equipment(e, locale)}
}

// AdminListEquipment implements adminListEquipment.
func (h Catalog) AdminListEquipment(ctx context.Context, _ gen.AdminListEquipmentRequestObject) (gen.AdminListEquipmentResponseObject, error) {
	items, err := h.admin.ListEquipment(ctx)
	if err != nil {
		return nil, err
	}

	locale := i18n.FromContext(ctx)
	data := make([]gen.Equipment, len(items))

	for i, e := range items {
		data[i] = equipment(e, locale)
	}

	return gen.AdminListEquipment200JSONResponse{Data: data}, nil
}

// AdminGetEquipment implements adminGetEquipment.
func (h Catalog) AdminGetEquipment(ctx context.Context, req gen.AdminGetEquipmentRequestObject) (gen.AdminGetEquipmentResponseObject, error) {
	e, err := h.admin.GetEquipment(ctx, int64(req.EquipmentId))
	if err != nil {
		return nil, err
	}

	return gen.AdminGetEquipment200JSONResponse{EquipmentResponseJSONResponse: equipmentResponse(e, i18n.FromContext(ctx))}, nil
}

// AdminCreateEquipment implements adminCreateEquipment.
func (h Catalog) AdminCreateEquipment(ctx context.Context, req gen.AdminCreateEquipmentRequestObject) (gen.AdminCreateEquipmentResponseObject, error) {
	e, err := h.admin.CreateEquipment(ctx, admin.EquipmentInput{
		DifficultyUnit: domain.DifficultyUnit(req.Body.DifficultyUnit), Translations: nameTranslations(req.Body.Translations),
	})
	if err != nil {
		return nil, err
	}

	return gen.AdminCreateEquipment201JSONResponse{EquipmentResponseJSONResponse: equipmentResponse(e, i18n.FromContext(ctx))}, nil
}

// AdminUpdateEquipment implements adminUpdateEquipment.
func (h Catalog) AdminUpdateEquipment(ctx context.Context, req gen.AdminUpdateEquipmentRequestObject) (gen.AdminUpdateEquipmentResponseObject, error) {
	e, err := h.admin.UpdateEquipment(ctx, int64(req.EquipmentId), admin.EquipmentInput{
		DifficultyUnit: domain.DifficultyUnit(req.Body.DifficultyUnit), Translations: nameTranslations(req.Body.Translations),
	})
	if err != nil {
		return nil, err
	}

	return gen.AdminUpdateEquipment200JSONResponse{EquipmentResponseJSONResponse: equipmentResponse(e, i18n.FromContext(ctx))}, nil
}

// AdminDeleteEquipment implements adminDeleteEquipment.
func (h Catalog) AdminDeleteEquipment(ctx context.Context, req gen.AdminDeleteEquipmentRequestObject) (gen.AdminDeleteEquipmentResponseObject, error) {
	if err := h.admin.DeleteEquipment(ctx, int64(req.EquipmentId)); err != nil {
		return nil, err
	}

	return gen.AdminDeleteEquipment204Response{}, nil
}

// --- Categories --------------------------------------------------------

func category(c admin.Category, locale i18n.Locale) gen.AdminCategory {
	out := gen.AdminCategory{Id: int(c.ID), Name: text(c.Name, locale), ExercisesCount: c.ExercisesCount}
	out.Translations.Name = translationMap(c.Name)

	return out
}

// AdminListCategories implements adminListCategories.
func (h Catalog) AdminListCategories(ctx context.Context, _ gen.AdminListCategoriesRequestObject) (gen.AdminListCategoriesResponseObject, error) {
	items, err := h.admin.ListCategories(ctx)
	if err != nil {
		return nil, err
	}

	locale := i18n.FromContext(ctx)
	data := make([]gen.AdminCategory, len(items))

	for i, c := range items {
		data[i] = category(c, locale)
	}

	return gen.AdminListCategories200JSONResponse{Data: data}, nil
}

// AdminGetCategory implements adminGetCategory.
func (h Catalog) AdminGetCategory(ctx context.Context, req gen.AdminGetCategoryRequestObject) (gen.AdminGetCategoryResponseObject, error) {
	c, err := h.admin.GetCategory(ctx, int64(req.CategoryId))
	if err != nil {
		return nil, err
	}

	return gen.AdminGetCategory200JSONResponse{AdminCategoryResponseJSONResponse: gen.AdminCategoryResponseJSONResponse{Data: category(c, i18n.FromContext(ctx))}}, nil
}

// AdminCreateCategory implements adminCreateCategory.
func (h Catalog) AdminCreateCategory(ctx context.Context, req gen.AdminCreateCategoryRequestObject) (gen.AdminCreateCategoryResponseObject, error) {
	c, err := h.admin.CreateCategory(ctx, nameTranslations(req.Body.Translations))
	if err != nil {
		return nil, err
	}

	return gen.AdminCreateCategory201JSONResponse{AdminCategoryResponseJSONResponse: gen.AdminCategoryResponseJSONResponse{Data: category(c, i18n.FromContext(ctx))}}, nil
}

// AdminUpdateCategory implements adminUpdateCategory.
func (h Catalog) AdminUpdateCategory(ctx context.Context, req gen.AdminUpdateCategoryRequestObject) (gen.AdminUpdateCategoryResponseObject, error) {
	c, err := h.admin.UpdateCategory(ctx, int64(req.CategoryId), nameTranslations(req.Body.Translations))
	if err != nil {
		return nil, err
	}

	return gen.AdminUpdateCategory200JSONResponse{AdminCategoryResponseJSONResponse: gen.AdminCategoryResponseJSONResponse{Data: category(c, i18n.FromContext(ctx))}}, nil
}

// AdminDeleteCategory implements adminDeleteCategory.
func (h Catalog) AdminDeleteCategory(ctx context.Context, req gen.AdminDeleteCategoryRequestObject) (gen.AdminDeleteCategoryResponseObject, error) {
	if err := h.admin.DeleteCategory(ctx, int64(req.CategoryId)); err != nil {
		return nil, err
	}

	return gen.AdminDeleteCategory204Response{}, nil
}

// --- Exercises ---------------------------------------------------------

// adminExercise is an exercise as the admin sees it; withEquipment is false
// for the create and update responses.
func adminExercise(e domain.Exercise, locale i18n.Locale, withEquipment bool) gen.AdminExercise {
	public := exercise(e, locale)
	out := gen.AdminExercise{
		Id: public.Id, Name: public.Name, Description: public.Description, EquipmentId: public.EquipmentId,
		EffortType: public.EffortType, RestTimeSeconds: public.RestTimeSeconds, CategoryIds: public.CategoryIds, Categories: public.Categories,
	}

	if withEquipment {
		out.EquipmentName, out.DifficultyUnit = public.EquipmentName, public.DifficultyUnit
	}

	out.Translations.Name = translationMap(e.Name)
	out.Translations.Description = translationMap(e.Description)

	return out
}

func exerciseInput(in gen.ExerciseWrite) admin.ExerciseInput {
	var categories []int64
	if in.CategoryIds != nil {
		for _, id := range *in.CategoryIds {
			categories = append(categories, int64(id))
		}
	}

	return admin.ExerciseInput{
		EquipmentID: int64(in.EquipmentId), EffortType: domain.EffortType(in.EffortType), RestTimeSeconds: in.RestTimeSeconds,
		CategoryIDs: categories, Translations: translations(in.Translations),
	}
}

// AdminListExercises implements adminListExercises.
func (h Catalog) AdminListExercises(ctx context.Context, _ gen.AdminListExercisesRequestObject) (gen.AdminListExercisesResponseObject, error) {
	items, err := h.admin.ListExercises(ctx)
	if err != nil {
		return nil, err
	}

	locale := i18n.FromContext(ctx)
	data := make([]gen.AdminExercise, len(items))

	for i, e := range items {
		data[i] = adminExercise(e, locale, true)
	}

	return gen.AdminListExercises200JSONResponse{Data: data}, nil
}

// AdminGetExercise implements adminGetExercise.
func (h Catalog) AdminGetExercise(ctx context.Context, req gen.AdminGetExerciseRequestObject) (gen.AdminGetExerciseResponseObject, error) {
	e, err := h.admin.GetExercise(ctx, int64(req.ExerciseId))
	if err != nil {
		return nil, err
	}

	return gen.AdminGetExercise200JSONResponse{AdminExerciseResponseJSONResponse: gen.AdminExerciseResponseJSONResponse{Data: adminExercise(e, i18n.FromContext(ctx), true)}}, nil
}

// AdminCreateExercise implements adminCreateExercise.
func (h Catalog) AdminCreateExercise(ctx context.Context, req gen.AdminCreateExerciseRequestObject) (gen.AdminCreateExerciseResponseObject, error) {
	e, err := h.admin.CreateExercise(ctx, exerciseInput(*req.Body))
	if err != nil {
		return nil, err
	}

	return gen.AdminCreateExercise201JSONResponse{AdminExerciseResponseJSONResponse: gen.AdminExerciseResponseJSONResponse{Data: adminExercise(e, i18n.FromContext(ctx), false)}}, nil
}

// AdminUpdateExercise implements adminUpdateExercise.
func (h Catalog) AdminUpdateExercise(ctx context.Context, req gen.AdminUpdateExerciseRequestObject) (gen.AdminUpdateExerciseResponseObject, error) {
	e, err := h.admin.UpdateExercise(ctx, int64(req.ExerciseId), exerciseInput(*req.Body))
	if err != nil {
		return nil, err
	}

	return gen.AdminUpdateExercise200JSONResponse{AdminExerciseResponseJSONResponse: gen.AdminExerciseResponseJSONResponse{Data: adminExercise(e, i18n.FromContext(ctx), false)}}, nil
}

// AdminDeleteExercise implements adminDeleteExercise.
func (h Catalog) AdminDeleteExercise(ctx context.Context, req gen.AdminDeleteExerciseRequestObject) (gen.AdminDeleteExerciseResponseObject, error) {
	if err := h.admin.DeleteExercise(ctx, int64(req.ExerciseId)); err != nil {
		return nil, err
	}

	return gen.AdminDeleteExercise204Response{}, nil
}

// --- Workout templates -------------------------------------------------

func adminTemplate(t domain.WorkoutTemplate, locale i18n.Locale) gen.AdminWorkoutTemplate {
	activities := workoutTemplate(t, locale).Activities
	out := gen.AdminWorkoutTemplate{Id: int(t.ID), Name: text(t.Name, locale), Activities: &activities}
	out.Translations.Name = translationMap(t.Name)
	out.Translations.Description = translationMap(t.Description)

	return out
}

func templateInput(in gen.WorkoutTemplateWrite) admin.TemplateInput {
	out := admin.TemplateInput{Translations: translations(in.Translations)}

	for _, a := range in.Activities {
		activity := admin.ActivityInput{ExerciseID: int64(a.ExerciseId), Order: a.Order}
		for _, s := range a.Sets {
			activity.Sets = append(activity.Sets, admin.SetInput{Order: s.Order, EffortValue: s.EffortValue, DifficultyValue: float64Of(s.DifficultyValue)})
		}

		out.Activities = append(out.Activities, activity)
	}

	return out
}

// AdminListWorkoutTemplates implements adminListWorkoutTemplates.
func (h Catalog) AdminListWorkoutTemplates(ctx context.Context, _ gen.AdminListWorkoutTemplatesRequestObject) (gen.AdminListWorkoutTemplatesResponseObject, error) {
	items, err := h.admin.ListTemplates(ctx)
	if err != nil {
		return nil, err
	}

	locale := i18n.FromContext(ctx)
	data := make([]gen.AdminWorkoutTemplate, len(items))

	for i, t := range items {
		data[i] = gen.AdminWorkoutTemplate{Id: int(t.ID), Name: text(t.Name, locale), ActivitiesCount: &items[i].ActivitiesCount}
		data[i].Translations.Name = translationMap(t.Name)
		data[i].Translations.Description = translationMap(t.Description)
	}

	return gen.AdminListWorkoutTemplates200JSONResponse{Data: data}, nil
}

// AdminGetWorkoutTemplate implements adminGetWorkoutTemplate.
func (h Catalog) AdminGetWorkoutTemplate(ctx context.Context, req gen.AdminGetWorkoutTemplateRequestObject) (gen.AdminGetWorkoutTemplateResponseObject, error) {
	t, err := h.admin.GetTemplate(ctx, int64(req.WorkoutTemplateId))
	if err != nil {
		return nil, err
	}

	return gen.AdminGetWorkoutTemplate200JSONResponse{AdminWorkoutTemplateResponseJSONResponse: gen.AdminWorkoutTemplateResponseJSONResponse{Data: adminTemplate(t, i18n.FromContext(ctx))}}, nil
}

// AdminCreateWorkoutTemplate implements adminCreateWorkoutTemplate.
func (h Catalog) AdminCreateWorkoutTemplate(ctx context.Context, req gen.AdminCreateWorkoutTemplateRequestObject) (gen.AdminCreateWorkoutTemplateResponseObject, error) {
	t, err := h.admin.CreateTemplate(ctx, templateInput(*req.Body))
	if err != nil {
		return nil, err
	}

	return gen.AdminCreateWorkoutTemplate201JSONResponse{AdminWorkoutTemplateResponseJSONResponse: gen.AdminWorkoutTemplateResponseJSONResponse{Data: adminTemplate(t, i18n.FromContext(ctx))}}, nil
}

// AdminUpdateWorkoutTemplate implements adminUpdateWorkoutTemplate.
func (h Catalog) AdminUpdateWorkoutTemplate(ctx context.Context, req gen.AdminUpdateWorkoutTemplateRequestObject) (gen.AdminUpdateWorkoutTemplateResponseObject, error) {
	t, err := h.admin.UpdateTemplate(ctx, int64(req.WorkoutTemplateId), templateInput(*req.Body))
	if err != nil {
		return nil, err
	}

	return gen.AdminUpdateWorkoutTemplate200JSONResponse{AdminWorkoutTemplateResponseJSONResponse: gen.AdminWorkoutTemplateResponseJSONResponse{Data: adminTemplate(t, i18n.FromContext(ctx))}}, nil
}

// AdminDeleteWorkoutTemplate implements adminDeleteWorkoutTemplate.
func (h Catalog) AdminDeleteWorkoutTemplate(ctx context.Context, req gen.AdminDeleteWorkoutTemplateRequestObject) (gen.AdminDeleteWorkoutTemplateResponseObject, error) {
	if err := h.admin.DeleteTemplate(ctx, int64(req.WorkoutTemplateId)); err != nil {
		return nil, err
	}

	return gen.AdminDeleteWorkoutTemplate204Response{}, nil
}

// --- Programs ----------------------------------------------------------

func adminProgram(p domain.Program, locale i18n.Locale) gen.AdminProgram {
	out := gen.AdminProgram{Id: int(p.ID), Name: text(p.Name, locale)}
	out.Translations.Name = translationMap(p.Name)
	out.Translations.Description = translationMap(p.Description)

	assignments := sliceOf(out.Assignments, len(p.Assignments))
	for i, a := range p.Assignments {
		item := &assignments[i]
		item.WorkoutTemplateId, item.WorkoutTemplateName, item.Weekday = int(a.Template.ID), text(a.Template.Name, locale), gen.Weekday(a.Weekday)
	}

	out.Assignments = &assignments

	return out
}

// sliceOf makes a slice of the type a generated field points to, whose
// element type is anonymous in the generated code.
func sliceOf[S ~[]E, E any](_ *S, n int) S {
	return make(S, n)
}

func programInput(in gen.ProgramWrite) admin.ProgramInput {
	out := admin.ProgramInput{Translations: translations(in.Translations)}
	for _, a := range in.Assignments {
		out.Assignments = append(out.Assignments, admin.AssignmentInput{TemplateID: int64(a.WorkoutTemplateId), Weekday: domain.Weekday(a.Weekday)})
	}

	return out
}

// AdminListPrograms implements adminListPrograms.
func (h Catalog) AdminListPrograms(ctx context.Context, _ gen.AdminListProgramsRequestObject) (gen.AdminListProgramsResponseObject, error) {
	items, err := h.admin.ListPrograms(ctx)
	if err != nil {
		return nil, err
	}

	locale := i18n.FromContext(ctx)
	data := make([]gen.AdminProgram, len(items))

	for i, p := range items {
		data[i] = gen.AdminProgram{Id: int(p.ID), Name: text(p.Name, locale), TemplatesCount: &items[i].TemplatesCount}
		data[i].Translations.Name = translationMap(p.Name)
		data[i].Translations.Description = translationMap(p.Description)
	}

	return gen.AdminListPrograms200JSONResponse{Data: data}, nil
}

// AdminGetProgram implements adminGetProgram.
func (h Catalog) AdminGetProgram(ctx context.Context, req gen.AdminGetProgramRequestObject) (gen.AdminGetProgramResponseObject, error) {
	p, err := h.admin.GetProgram(ctx, int64(req.ProgramId))
	if err != nil {
		return nil, err
	}

	return gen.AdminGetProgram200JSONResponse{AdminProgramResponseJSONResponse: gen.AdminProgramResponseJSONResponse{Data: adminProgram(p, i18n.FromContext(ctx))}}, nil
}

// AdminCreateProgram implements adminCreateProgram.
func (h Catalog) AdminCreateProgram(ctx context.Context, req gen.AdminCreateProgramRequestObject) (gen.AdminCreateProgramResponseObject, error) {
	p, err := h.admin.CreateProgram(ctx, programInput(*req.Body))
	if err != nil {
		return nil, err
	}

	return gen.AdminCreateProgram201JSONResponse{AdminProgramResponseJSONResponse: gen.AdminProgramResponseJSONResponse{Data: adminProgram(p, i18n.FromContext(ctx))}}, nil
}

// AdminUpdateProgram implements adminUpdateProgram.
func (h Catalog) AdminUpdateProgram(ctx context.Context, req gen.AdminUpdateProgramRequestObject) (gen.AdminUpdateProgramResponseObject, error) {
	p, err := h.admin.UpdateProgram(ctx, int64(req.ProgramId), programInput(*req.Body))
	if err != nil {
		return nil, err
	}

	return gen.AdminUpdateProgram200JSONResponse{AdminProgramResponseJSONResponse: gen.AdminProgramResponseJSONResponse{Data: adminProgram(p, i18n.FromContext(ctx))}}, nil
}

// AdminDeleteProgram implements adminDeleteProgram.
func (h Catalog) AdminDeleteProgram(ctx context.Context, req gen.AdminDeleteProgramRequestObject) (gen.AdminDeleteProgramResponseObject, error) {
	if err := h.admin.DeleteProgram(ctx, int64(req.ProgramId)); err != nil {
		return nil, err
	}

	return gen.AdminDeleteProgram204Response{}, nil
}

func float64Of(v *float32) *float64 {
	if v == nil {
		return nil
	}

	f := float64(*v)

	return &f
}

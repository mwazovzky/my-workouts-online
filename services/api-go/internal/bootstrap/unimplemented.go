package bootstrap

import (
	"context"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// unimplemented answers every contract operation no story has implemented
// yet with 501 (httpapi.ErrNotImplemented). It sits in api next to the
// module handlers at the same depth, so a story that implements an
// operation must delete its stub here: two methods of one name would make
// the call ambiguous and api would stop compiling.
type unimplemented struct{}

func (unimplemented) AdminListCategories(context.Context, gen.AdminListCategoriesRequestObject) (gen.AdminListCategoriesResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminCreateCategory(context.Context, gen.AdminCreateCategoryRequestObject) (gen.AdminCreateCategoryResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminDeleteCategory(context.Context, gen.AdminDeleteCategoryRequestObject) (gen.AdminDeleteCategoryResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminGetCategory(context.Context, gen.AdminGetCategoryRequestObject) (gen.AdminGetCategoryResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminUpdateCategory(context.Context, gen.AdminUpdateCategoryRequestObject) (gen.AdminUpdateCategoryResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminListEquipment(context.Context, gen.AdminListEquipmentRequestObject) (gen.AdminListEquipmentResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminCreateEquipment(context.Context, gen.AdminCreateEquipmentRequestObject) (gen.AdminCreateEquipmentResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminDeleteEquipment(context.Context, gen.AdminDeleteEquipmentRequestObject) (gen.AdminDeleteEquipmentResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminGetEquipment(context.Context, gen.AdminGetEquipmentRequestObject) (gen.AdminGetEquipmentResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminUpdateEquipment(context.Context, gen.AdminUpdateEquipmentRequestObject) (gen.AdminUpdateEquipmentResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminListExercises(context.Context, gen.AdminListExercisesRequestObject) (gen.AdminListExercisesResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminCreateExercise(context.Context, gen.AdminCreateExerciseRequestObject) (gen.AdminCreateExerciseResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminDeleteExercise(context.Context, gen.AdminDeleteExerciseRequestObject) (gen.AdminDeleteExerciseResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminGetExercise(context.Context, gen.AdminGetExerciseRequestObject) (gen.AdminGetExerciseResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminUpdateExercise(context.Context, gen.AdminUpdateExerciseRequestObject) (gen.AdminUpdateExerciseResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminListPrograms(context.Context, gen.AdminListProgramsRequestObject) (gen.AdminListProgramsResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminCreateProgram(context.Context, gen.AdminCreateProgramRequestObject) (gen.AdminCreateProgramResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminDeleteProgram(context.Context, gen.AdminDeleteProgramRequestObject) (gen.AdminDeleteProgramResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminGetProgram(context.Context, gen.AdminGetProgramRequestObject) (gen.AdminGetProgramResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminUpdateProgram(context.Context, gen.AdminUpdateProgramRequestObject) (gen.AdminUpdateProgramResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminListWorkoutTemplates(context.Context, gen.AdminListWorkoutTemplatesRequestObject) (gen.AdminListWorkoutTemplatesResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminCreateWorkoutTemplate(context.Context, gen.AdminCreateWorkoutTemplateRequestObject) (gen.AdminCreateWorkoutTemplateResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminDeleteWorkoutTemplate(context.Context, gen.AdminDeleteWorkoutTemplateRequestObject) (gen.AdminDeleteWorkoutTemplateResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminGetWorkoutTemplate(context.Context, gen.AdminGetWorkoutTemplateRequestObject) (gen.AdminGetWorkoutTemplateResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) AdminUpdateWorkoutTemplate(context.Context, gen.AdminUpdateWorkoutTemplateRequestObject) (gen.AdminUpdateWorkoutTemplateResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

func (unimplemented) SocialLogin(context.Context, gen.SocialLoginRequestObject) (gen.SocialLoginResponseObject, error) {
	return nil, httpapi.ErrNotImplemented
}

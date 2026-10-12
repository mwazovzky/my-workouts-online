// Package catalog is the catalog module: equipment, categories, exercises,
// workout templates with their activities and sets, programs and
// enrollment. It owns those tables (migrations/00002_catalog.sql) and
// depends on no other module; training and dashboard read it through
// catalogapi only.
//
//	domain/            Exercise, WorkoutTemplate, Program, LocalizedText, the enums
//	ports/             the repositories the use cases need
//	app/               the use cases (Service), which also implement catalogapi.Catalog
//	catalogapi/        the DTOs and interface other modules use, and its Fake
//	adapters/postgres  repositories over sqlc (sql/catalog)
//	adapters/http      listPrograms, getProgram, enrollProgram, unenrollProgram,
//	                   getWorkoutTemplate, listExercises
package catalog

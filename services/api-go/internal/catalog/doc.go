// Package catalog is the catalog module: equipment, categories, exercises,
// workout templates with their activities and sets, programs and
// enrollment, and the sync that applies the repository's YAML catalog to
// them (ADR 0009). It owns those tables (migrations/00002_catalog.sql) and
// depends on no other module; training and dashboard read it through
// catalogapi only. The sync's relabelling warning is its one read of
// another module's table (training's workout_activities).
//
//	domain/            Exercise, WorkoutTemplate, Program, LocalizedText, the enums
//	ports/             the repositories the use cases need
//	app/               the use cases (Service), which also implement catalogapi.Catalog
//	app/catalogsync    validation rules and the sync (Syncer, Report)
//	catalogapi/        the DTOs and interface other modules use, and its Fake
//	adapters/postgres  repositories and the sync store over sqlc (sql/catalog)
//	adapters/catalogfile  the YAML catalog loader
//	adapters/http      listPrograms, getProgram, enrollProgram, unenrollProgram,
//	                   getWorkoutTemplate, listExercises
package catalog

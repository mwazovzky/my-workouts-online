// Package training is the training module: a user's workouts with their
// activities and sets. It owns the workouts, workout_activities and
// workout_sets tables (migrations/00003_training.sql) and reads the catalog
// only through catalogapi; the dashboard reads it through trainingapi.
//
//	domain/            Workout, Activity, Set, Status and their invariants
//	ports/             the Workouts repository and the Catalog it reads
//	app/               the use cases (Service), which also implement trainingapi.Training
//	trainingapi/       the DTOs and interface the dashboard uses, and its Fake
//	adapters/postgres  the repository over sqlc (sql/training)
//	adapters/http      listWorkouts, createWorkout, getWorkout, replaceWorkoutActivities,
//	                   completeWorkout, repeatWorkout, deleteWorkout
package training

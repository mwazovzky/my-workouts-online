-- name: ListEquipmentRows :many
SELECT id, slug, name FROM equipment ORDER BY id;

-- name: ListCategoryRows :many
SELECT id, slug, name FROM categories ORDER BY id;

-- name: ListExerciseRows :many
-- Retired exercises included: re-adopting one restores it.
SELECT id, slug, name, (deleted_at IS NOT NULL)::boolean AS retired FROM exercises ORDER BY id;

-- name: ListWorkoutTemplateRows :many
SELECT id, slug, name FROM workout_templates ORDER BY id;

-- name: ListProgramRows :many
SELECT id, slug, name FROM programs ORDER BY id;

-- name: GetEquipmentRecord :one
SELECT name, translations, difficulty_unit FROM equipment WHERE id = $1;

-- name: GetCategoryRecord :one
SELECT name, translations FROM categories WHERE id = $1;

-- name: GetExerciseRecord :one
SELECT name, description, translations, equipment_id, effort_type, rest_time_seconds
FROM exercises
WHERE id = $1;

-- name: ListExerciseCategoryIDs :many
SELECT category_id FROM category_exercise WHERE exercise_id = $1 ORDER BY category_id;

-- name: GetProgramRecord :one
SELECT name, description, translations FROM programs WHERE id = $1;

-- name: ListProgramSchedule :many
SELECT template_id, weekday FROM program_workout_template WHERE program_id = $1 ORDER BY id;

-- name: CreateEquipment :one
INSERT INTO equipment (slug, name, difficulty_unit, translations, created_at, updated_at)
VALUES (@slug, @name, @difficulty_unit, @translations, @now, @now)
RETURNING id;

-- name: UpdateEquipment :exec
UPDATE equipment
SET name = @name, difficulty_unit = @difficulty_unit, translations = @translations, updated_at = @now
WHERE id = @id;

-- name: CreateCategory :one
INSERT INTO categories (slug, name, translations, created_at, updated_at)
VALUES (@slug, @name, @translations, @now, @now)
RETURNING id;

-- name: UpdateCategory :exec
UPDATE categories SET name = @name, translations = @translations, updated_at = @now WHERE id = @id;

-- name: CreateExercise :one
INSERT INTO exercises (slug, name, description, equipment_id, effort_type, rest_time_seconds, translations, created_at, updated_at)
VALUES (@slug, @name, @description, @equipment_id, @effort_type, @rest_time_seconds, @translations, @now, @now)
RETURNING id;

-- name: UpdateExercise :exec
-- An exercise the files list is active: updating it restores it.
UPDATE exercises
SET name = @name, description = @description, equipment_id = @equipment_id, effort_type = @effort_type,
    rest_time_seconds = @rest_time_seconds, translations = @translations, deleted_at = NULL, updated_at = @now
WHERE id = @id;

-- name: DeleteExerciseCategories :exec
DELETE FROM category_exercise WHERE exercise_id = $1;

-- name: InsertExerciseCategories :exec
INSERT INTO category_exercise (exercise_id, category_id)
SELECT @exercise_id::bigint, unnest(@category_ids::bigint[]);

-- name: CreateWorkoutTemplate :one
INSERT INTO workout_templates (slug, name, description, translations, created_at, updated_at)
VALUES (@slug, @name, @description, @translations, @now, @now)
RETURNING id;

-- name: UpdateWorkoutTemplate :exec
UPDATE workout_templates
SET name = @name, description = @description, translations = @translations, updated_at = @now
WHERE id = @id;

-- name: DeleteTemplateActivities :exec
DELETE FROM template_activities WHERE template_id = $1;

-- name: InsertTemplateActivity :one
INSERT INTO template_activities (template_id, exercise_id, "order", created_at, updated_at)
VALUES (@template_id, @exercise_id, @position, @now, @now)
RETURNING id;

-- name: InsertTemplateSet :exec
INSERT INTO template_sets (activity_id, "order", effort_value, difficulty_value, created_at, updated_at)
VALUES (@activity_id, @position, @effort_value, @difficulty_value, @now, @now);

-- name: CreateProgram :one
INSERT INTO programs (slug, name, description, translations, created_at, updated_at)
VALUES (@slug, @name, @description, @translations, @now, @now)
RETURNING id;

-- name: UpdateProgram :exec
UPDATE programs
SET name = @name, description = @description, translations = @translations, updated_at = @now
WHERE id = @id;

-- name: DeleteProgramAssignments :exec
DELETE FROM program_workout_template WHERE program_id = $1;

-- name: InsertProgramAssignment :exec
INSERT INTO program_workout_template (program_id, template_id, weekday) VALUES ($1, $2, $3);

-- name: SetEquipmentSlug :exec
UPDATE equipment SET slug = $2 WHERE id = $1;

-- name: SetCategorySlug :exec
UPDATE categories SET slug = $2 WHERE id = $1;

-- name: SetExerciseSlug :exec
UPDATE exercises SET slug = $2 WHERE id = $1;

-- name: SetWorkoutTemplateSlug :exec
UPDATE workout_templates SET slug = $2 WHERE id = $1;

-- name: SetProgramSlug :exec
UPDATE programs SET slug = $2 WHERE id = $1;

-- name: RetireExercise :exec
UPDATE exercises SET deleted_at = $2, updated_at = $2 WHERE id = $1;

-- name: DeleteEquipment :exec
DELETE FROM equipment WHERE id = $1;

-- name: DeleteCategory :exec
DELETE FROM categories WHERE id = $1;

-- name: DeleteWorkoutTemplate :exec
DELETE FROM workout_templates WHERE id = $1;

-- name: DeleteProgram :exec
DELETE FROM programs WHERE id = $1;

-- name: EquipmentInUse :one
-- Retired exercises count: their history still names the equipment.
SELECT EXISTS (SELECT 1 FROM exercises WHERE equipment_id = $1);

-- name: CategoryInUse :one
SELECT EXISTS (SELECT 1 FROM category_exercise WHERE category_id = $1);

-- name: WorkoutTemplateInUse :one
SELECT EXISTS (SELECT 1 FROM program_workout_template WHERE template_id = $1);

-- name: ProgramHasEnrollments :one
SELECT EXISTS (SELECT 1 FROM program_user WHERE program_id = $1);

-- name: ListEquipmentExerciseIDs :many
SELECT id FROM exercises WHERE equipment_id = $1 ORDER BY id;

-- name: HasLoggedActivities :one
-- Reads training's table: whether a workout logs one of the exercises. The
-- only cross-module read in catalog, for the sync's relabelling warning.
SELECT EXISTS (SELECT 1 FROM workout_activities WHERE exercise_id = ANY (@exercise_ids::bigint[]));

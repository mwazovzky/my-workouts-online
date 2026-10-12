-- name: ListActiveExercises :many
-- The picker: retired exercises are left out.
SELECT e.id, e.name, e.description, e.translations, e.effort_type, e.rest_time_seconds, e.deleted_at,
       eq.id AS equipment_id, eq.name AS equipment_name, eq.translations AS equipment_translations,
       eq.difficulty_unit
FROM exercises e
JOIN equipment eq ON eq.id = e.equipment_id
WHERE e.deleted_at IS NULL
ORDER BY e.id;

-- name: ListExercisesByID :many
-- Retired exercises included: history and templates still resolve them.
SELECT e.id, e.name, e.description, e.translations, e.effort_type, e.rest_time_seconds, e.deleted_at,
       eq.id AS equipment_id, eq.name AS equipment_name, eq.translations AS equipment_translations,
       eq.difficulty_unit
FROM exercises e
JOIN equipment eq ON eq.id = e.equipment_id
WHERE e.id = ANY (@ids::bigint[])
ORDER BY e.id;

-- name: ListExerciseCategories :many
SELECT ce.exercise_id, c.id, c.name, c.translations
FROM category_exercise ce
JOIN categories c ON c.id = ce.category_id
WHERE ce.exercise_id = ANY (@exercise_ids::bigint[])
ORDER BY ce.exercise_id, c.id;

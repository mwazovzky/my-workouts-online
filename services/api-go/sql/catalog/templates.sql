-- name: GetWorkoutTemplate :one
SELECT id, name, description, translations
FROM workout_templates
WHERE id = $1;

-- name: ListTemplateActivities :many
SELECT id, exercise_id, "order"
FROM template_activities
WHERE template_id = $1
ORDER BY "order";

-- name: ListTemplateSets :many
SELECT s.id, s.activity_id, s."order", s.effort_value, s.difficulty_value
FROM template_sets s
JOIN template_activities a ON a.id = s.activity_id
WHERE a.template_id = $1
ORDER BY s.activity_id, s."order";

-- name: ListWorkoutTemplatesByID :many
SELECT id, name, description, translations
FROM workout_templates
WHERE id = ANY (@ids::bigint[])
ORDER BY id;

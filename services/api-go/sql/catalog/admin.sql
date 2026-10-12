-- name: ListAdminEquipment :many
-- With the active exercises using each item, as PHP's withCount.
SELECT e.id, e.name, e.translations, e.difficulty_unit,
       (SELECT count(*) FROM exercises x WHERE x.equipment_id = e.id AND x.deleted_at IS NULL)::integer AS exercises_count
FROM equipment e
ORDER BY e.id;

-- name: ListAdminCategories :many
SELECT c.id, c.name, c.translations,
       (SELECT count(*) FROM category_exercise ce JOIN exercises x ON x.id = ce.exercise_id
        WHERE ce.category_id = c.id AND x.deleted_at IS NULL)::integer AS exercises_count
FROM categories c
ORDER BY c.id;

-- name: ListAdminWorkoutTemplates :many
SELECT t.id, t.name, t.description, t.translations,
       (SELECT count(*) FROM template_activities a WHERE a.template_id = t.id)::integer AS activities_count
FROM workout_templates t
ORDER BY t.id;

-- name: ListAdminPrograms :many
-- templates_count counts assignments, a template on two weekdays twice.
SELECT p.id, p.name, p.description, p.translations,
       (SELECT count(*) FROM program_workout_template a WHERE a.program_id = p.id)::integer AS templates_count
FROM programs p
ORDER BY p.id;

-- name: ExistingEquipmentIDs :many
SELECT id FROM equipment WHERE id = ANY (@ids::bigint[]);

-- name: ExistingCategoryIDs :many
SELECT id FROM categories WHERE id = ANY (@ids::bigint[]);

-- name: ExistingWorkoutTemplateIDs :many
SELECT id FROM workout_templates WHERE id = ANY (@ids::bigint[]);

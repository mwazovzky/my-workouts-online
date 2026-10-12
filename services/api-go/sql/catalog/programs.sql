-- name: ListPrograms :many
-- Every program in id order, with whether the user is enrolled.
SELECT p.id, p.name, p.description, p.translations,
       EXISTS (SELECT 1 FROM program_user pu WHERE pu.program_id = p.id AND pu.user_id = $1) AS enrolled
FROM programs p
ORDER BY p.id;

-- name: GetProgram :one
SELECT p.id, p.name, p.description, p.translations,
       EXISTS (SELECT 1 FROM program_user pu WHERE pu.program_id = p.id AND pu.user_id = @user_id) AS enrolled
FROM programs p
WHERE p.id = @id;

-- name: ListProgramAssignments :many
-- In the order the templates were assigned, so a template scheduled on
-- several weekdays keeps PHP's order.
SELECT pwt.weekday, t.id, t.name, t.description, t.translations
FROM program_workout_template pwt
JOIN workout_templates t ON t.id = pwt.template_id
WHERE pwt.program_id = $1
ORDER BY pwt.id;

-- name: ProgramExists :one
SELECT EXISTS (SELECT 1 FROM programs WHERE id = $1);

-- name: Enroll :exec
INSERT INTO program_user (program_id, user_id, enrolled_at)
VALUES ($1, $2, $3)
ON CONFLICT ON CONSTRAINT program_user_pkey DO NOTHING;

-- name: Unenroll :exec
DELETE FROM program_user WHERE program_id = $1 AND user_id = $2;

-- name: ListEnrolledSchedule :many
-- Every assignment of the programs the user is enrolled in, by program and
-- then in assignment order.
SELECT p.id AS program_id, p.name AS program_name, p.translations AS program_translations,
       t.id AS template_id, t.name AS template_name, t.translations AS template_translations,
       pwt.weekday
FROM program_user pu
JOIN programs p ON p.id = pu.program_id
JOIN program_workout_template pwt ON pwt.program_id = p.id
JOIN workout_templates t ON t.id = pwt.template_id
WHERE pu.user_id = $1
ORDER BY p.id, pwt.id;

-- name: CountEnrolledPrograms :one
SELECT count(*)::integer FROM program_user WHERE user_id = $1;

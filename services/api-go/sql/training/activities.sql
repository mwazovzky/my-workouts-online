-- name: ListWorkoutActivities :many
SELECT id, exercise_id, "order" FROM workout_activities WHERE workout_id = $1 ORDER BY "order";

-- name: ListWorkoutSets :many
SELECT s.id, s.activity_id, s."order", s.effort_value, s.difficulty_value, s.is_completed
FROM workout_sets s
JOIN workout_activities a ON a.id = s.activity_id
WHERE a.workout_id = $1
ORDER BY s.activity_id, s."order";

-- name: InsertWorkoutActivity :one
INSERT INTO workout_activities (workout_id, exercise_id, "order", created_at, updated_at)
VALUES (@workout_id, @exercise_id, @position, @now, @now)
RETURNING id;

-- name: InsertWorkoutSet :one
INSERT INTO workout_sets (activity_id, "order", effort_value, difficulty_value, is_completed, created_at, updated_at)
VALUES (@activity_id, @position, @effort_value, @difficulty_value, @is_completed, @now, @now)
RETURNING id;

-- name: DeleteWorkoutActivitiesExcept :exec
-- Activities the replace does not keep go, their sets with them.
DELETE FROM workout_activities WHERE workout_id = @workout_id AND NOT (id = ANY (@keep::bigint[]));

-- name: ParkWorkoutActivities :exec
-- Kept activities move to order -id first, so the new orders never collide
-- with old ones under the (workout_id, order) unique key.
UPDATE workout_activities SET "order" = -id WHERE workout_id = @workout_id AND id = ANY (@ids::bigint[]);

-- name: UpdateWorkoutActivity :exec
UPDATE workout_activities SET exercise_id = @exercise_id, "order" = @position, updated_at = @now WHERE id = @id;

-- name: DeleteWorkoutSetsExcept :exec
DELETE FROM workout_sets WHERE activity_id = @activity_id AND NOT (id = ANY (@keep::bigint[]));

-- name: ParkWorkoutSets :exec
UPDATE workout_sets SET "order" = -id WHERE activity_id = @activity_id AND id = ANY (@ids::bigint[]);

-- name: UpdateWorkoutSet :exec
UPDATE workout_sets
SET "order" = @position, effort_value = @effort_value, difficulty_value = @difficulty_value,
    is_completed = @is_completed, updated_at = @now
WHERE id = @id;

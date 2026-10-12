-- name: CreateWorkout :one
INSERT INTO workouts (user_id, template_id, name, status, version, created_at, updated_at)
VALUES (@user_id, @template_id, @name, @status, 1, @now, @now)
RETURNING *;

-- name: GetOwnedWorkout :one
SELECT * FROM workouts WHERE id = @id AND user_id = @user_id;

-- name: LockWorkout :one
-- Serialises changes to one workout: the If-Match check and the write run
-- under this lock.
SELECT * FROM workouts WHERE id = $1 FOR UPDATE;

-- name: ListOwnedWorkouts :many
SELECT w.*, (SELECT count(*) FROM workout_activities a WHERE a.workout_id = w.id)::integer AS activities_count
FROM workouts w
WHERE w.user_id = @user_id
ORDER BY w.updated_at DESC, w.id DESC
LIMIT @page_size OFFSET @page_offset::bigint;

-- name: CountOwnedWorkouts :one
SELECT count(*)::integer FROM workouts WHERE user_id = $1;

-- name: InProgressWorkout :one
-- The most recently updated unfinished workout.
SELECT w.*, (SELECT count(*) FROM workout_activities a WHERE a.workout_id = w.id)::integer AS activities_count
FROM workouts w
WHERE w.user_id = $1 AND w.status = 'in_progress'
ORDER BY w.updated_at DESC, w.id DESC
LIMIT 1;

-- name: CountCompletedWorkouts :one
SELECT count(*)::integer AS completed,
       (count(*) FILTER (WHERE updated_at >= @since))::integer AS completed_since
FROM workouts
WHERE user_id = @user_id AND status = 'completed';

-- name: UpdateWorkoutStatus :exec
UPDATE workouts SET status = @status, version = @version, updated_at = @now WHERE id = @id;

-- name: DeleteWorkout :exec
DELETE FROM workouts WHERE id = $1;

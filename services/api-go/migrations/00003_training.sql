-- Training: workouts with their activities and sets. The training module owns
-- these tables. Creating a workout copies a template snapshot into them, so a
-- workout never reads the template tables again (ADR 0005).

-- +goose Up
CREATE TABLE workouts (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id     bigint      NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    -- History outlives its template.
    template_id bigint      REFERENCES workout_templates (id) ON DELETE SET NULL,
    -- The template's name in the user's locale when the workout was created.
    name        text        NOT NULL,
    status      text        NOT NULL DEFAULT 'in_progress'
        CONSTRAINT workouts_status_check CHECK (status IN ('in_progress', 'completed')),
    -- The ETag counter: bumped by every activities replace and by complete.
    version     integer     NOT NULL DEFAULT 1
        CONSTRAINT workouts_version_check CHECK (version > 0),
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);

-- The workout list: one user's workouts, most recently updated first.
CREATE INDEX workouts_user_id_updated_at_idx ON workouts (user_id, updated_at DESC, id DESC);
CREATE INDEX workouts_template_id_idx ON workouts (template_id);

CREATE TABLE workout_activities (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workout_id  bigint      NOT NULL REFERENCES workouts (id) ON DELETE CASCADE,
    -- Exercises are retired, not deleted; a hard delete must not erase history.
    exercise_id bigint      NOT NULL REFERENCES exercises (id) ON DELETE RESTRICT,
    "order"     integer     NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT workout_activities_workout_id_order_key UNIQUE (workout_id, "order")
);

CREATE INDEX workout_activities_exercise_id_idx ON workout_activities (exercise_id);

CREATE TABLE workout_sets (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    activity_id      bigint        NOT NULL REFERENCES workout_activities (id) ON DELETE CASCADE,
    "order"          integer       NOT NULL,
    effort_value     integer       NOT NULL
        CONSTRAINT workout_sets_effort_value_check CHECK (effort_value >= 0),
    difficulty_value numeric(6, 1),
    is_completed     boolean       NOT NULL DEFAULT false,
    created_at       timestamptz   NOT NULL DEFAULT now(),
    updated_at       timestamptz   NOT NULL DEFAULT now(),
    CONSTRAINT workout_sets_activity_id_order_key UNIQUE (activity_id, "order")
);

-- +goose Down
DROP TABLE workout_sets;
DROP TABLE workout_activities;
DROP TABLE workouts;

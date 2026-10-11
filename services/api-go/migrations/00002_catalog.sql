-- Catalog: equipment, categories, exercises, workout templates, programs and
-- enrollment. The catalog module owns these tables.
--
-- Translatable rows keep their English source text in name/description and
-- every other locale in translations, shaped {"ru": {"name": …}} (ADR 0004).
-- Template activities and sets are their own tables, not shared with
-- workouts (ADR 0005). Rows with a slug are managed by catalog sync; rows
-- without one were created by an admin.

-- +goose Up
CREATE TABLE equipment (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug            text        CONSTRAINT equipment_slug_key UNIQUE,
    name            text        NOT NULL,
    difficulty_unit text        NOT NULL DEFAULT 'kilograms'
        CONSTRAINT equipment_difficulty_unit_check
        CHECK (difficulty_unit IN ('kilograms', 'pounds', 'plates', 'heart_rate_zone', 'none')),
    translations    jsonb       NOT NULL DEFAULT '{}'
        CONSTRAINT equipment_translations_check CHECK (jsonb_typeof(translations) = 'object'),
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE categories (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug         text        CONSTRAINT categories_slug_key UNIQUE,
    name         text        NOT NULL,
    translations jsonb       NOT NULL DEFAULT '{}'
        CONSTRAINT categories_translations_check CHECK (jsonb_typeof(translations) = 'object'),
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE exercises (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug              text        CONSTRAINT exercises_slug_key UNIQUE,
    name              text        NOT NULL,
    description       text,
    -- Equipment in use by any exercise, retired ones included, cannot be deleted.
    equipment_id      bigint      NOT NULL REFERENCES equipment (id) ON DELETE RESTRICT,
    effort_type       text        NOT NULL DEFAULT 'repetitions'
        CONSTRAINT exercises_effort_type_check CHECK (effort_type IN ('repetitions', 'duration')),
    rest_time_seconds integer     DEFAULT 90
        CONSTRAINT exercises_rest_time_seconds_check CHECK (rest_time_seconds >= 0),
    translations      jsonb       NOT NULL DEFAULT '{}'
        CONSTRAINT exercises_translations_check CHECK (jsonb_typeof(translations) = 'object'),
    -- Set when the exercise is retired; history keeps resolving it.
    deleted_at        timestamptz,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX exercises_equipment_id_idx ON exercises (equipment_id);

CREATE TABLE category_exercise (
    category_id bigint NOT NULL REFERENCES categories (id) ON DELETE CASCADE,
    exercise_id bigint NOT NULL REFERENCES exercises (id) ON DELETE CASCADE,
    CONSTRAINT category_exercise_pkey PRIMARY KEY (category_id, exercise_id)
);

CREATE INDEX category_exercise_exercise_id_idx ON category_exercise (exercise_id);

CREATE TABLE workout_templates (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug         text        CONSTRAINT workout_templates_slug_key UNIQUE,
    name         text        NOT NULL,
    description  text,
    translations jsonb       NOT NULL DEFAULT '{}'
        CONSTRAINT workout_templates_translations_check CHECK (jsonb_typeof(translations) = 'object'),
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE template_activities (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    template_id bigint      NOT NULL REFERENCES workout_templates (id) ON DELETE CASCADE,
    -- Exercises are retired, not deleted; a hard delete must not empty templates.
    exercise_id bigint      NOT NULL REFERENCES exercises (id) ON DELETE RESTRICT,
    "order"     integer     NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT template_activities_template_id_order_key UNIQUE (template_id, "order")
);

CREATE INDEX template_activities_exercise_id_idx ON template_activities (exercise_id);

CREATE TABLE template_sets (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    activity_id      bigint        NOT NULL REFERENCES template_activities (id) ON DELETE CASCADE,
    "order"          integer       NOT NULL,
    effort_value     integer       NOT NULL
        CONSTRAINT template_sets_effort_value_check CHECK (effort_value >= 0),
    difficulty_value numeric(6, 1),
    created_at       timestamptz   NOT NULL DEFAULT now(),
    updated_at       timestamptz   NOT NULL DEFAULT now(),
    CONSTRAINT template_sets_activity_id_order_key UNIQUE (activity_id, "order")
);

CREATE TABLE programs (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug         text        CONSTRAINT programs_slug_key UNIQUE,
    name         text        NOT NULL,
    description  text,
    translations jsonb       NOT NULL DEFAULT '{}'
        CONSTRAINT programs_translations_check CHECK (jsonb_typeof(translations) = 'object'),
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now()
);

-- No unique key: a program may schedule the same template on several weekdays,
-- and even twice on one.
CREATE TABLE program_workout_template (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    program_id  bigint NOT NULL REFERENCES programs (id) ON DELETE CASCADE,
    -- A template a program schedules cannot be deleted (409 in_use).
    template_id bigint NOT NULL REFERENCES workout_templates (id) ON DELETE RESTRICT,
    weekday     text   NOT NULL
        CONSTRAINT program_workout_template_weekday_check
        CHECK (weekday IN ('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'))
);

CREATE INDEX program_workout_template_program_id_idx ON program_workout_template (program_id);
CREATE INDEX program_workout_template_template_id_idx ON program_workout_template (template_id);

CREATE TABLE program_user (
    program_id  bigint      NOT NULL REFERENCES programs (id) ON DELETE CASCADE,
    user_id     bigint      NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    enrolled_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT program_user_pkey PRIMARY KEY (program_id, user_id)
);

CREATE INDEX program_user_user_id_idx ON program_user (user_id);

-- +goose Down
DROP TABLE program_user;
DROP TABLE program_workout_template;
DROP TABLE programs;
DROP TABLE template_sets;
DROP TABLE template_activities;
DROP TABLE workout_templates;
DROP TABLE category_exercise;
DROP TABLE exercises;
DROP TABLE categories;
DROP TABLE equipment;

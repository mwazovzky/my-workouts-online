-- The catalog the *.golden.json files were captured from: the same rows, with
-- the same ids, as php/GoGoldenCaptureTest.php builds in the PHP API (see
-- README.md here). Change both or neither.

INSERT INTO equipment (id, name, difficulty_unit, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Barbell', 'kilograms', '{"ru": {"name": "Штанга"}}'),
    (2, 'Bodyweight', 'none', '{"ru": {"name": "Собственный вес"}}'),
    (3, 'Dumbbell', 'pounds', '{}');

INSERT INTO categories (id, name, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Chest', '{"ru": {"name": "Грудь"}}'),
    (2, 'Legs', '{"ru": {"name": "Ноги"}}'),
    (3, 'Back', '{}');

INSERT INTO exercises (id, name, description, equipment_id, effort_type, rest_time_seconds, translations, deleted_at) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Squat', 'Feet shoulder-width apart.', 1, 'repetitions', 120,
        '{"ru": {"name": "Приседания", "description": "Ноги на ширине плеч."}}', NULL),
    (2, 'bench press', NULL, 1, 'repetitions', 90, '{"ru": {"name": "Жим лёжа"}}', NULL),
    (3, 'Push-up', 'Keep the body straight.', 2, 'repetitions', 60, '{"ru": {"name": "Отжимания"}}', NULL),
    (4, 'Upright Row', NULL, 3, 'repetitions', 90, '{"ru": {"name": "Тяга к подбородку"}}', '2026-10-01 12:00:00+00'),
    (5, 'Plank', NULL, 2, 'duration', NULL, '{}', NULL),
    (6, 'Deadlift', NULL, 1, 'repetitions', 180, '{"ru": {"name": "Становая тяга"}}', NULL),
    (7, 'Burpee', NULL, 2, 'repetitions', 30, '{"ru": {"name": "Бёрпи"}}', NULL);

INSERT INTO category_exercise (exercise_id, category_id) VALUES
    (1, 2), (2, 1), (3, 1), (4, 3), (6, 3), (6, 2);

INSERT INTO workout_templates (id, name, description, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Full Body A', 'Squat, push-up, row.',
        '{"ru": {"name": "Всё тело А", "description": "Присед, отжимания, тяга."}}'),
    (2, 'Core', NULL, '{}');

-- Activity and set ids do not follow their order, so the tests see the order
-- column win.
INSERT INTO template_activities (id, template_id, exercise_id, "order") OVERRIDING SYSTEM VALUE VALUES
    (1, 1, 3, 2),
    (2, 1, 1, 1),
    (3, 1, 4, 3),
    (4, 2, 5, 1);

INSERT INTO template_sets (id, activity_id, "order", effort_value, difficulty_value) OVERRIDING SYSTEM VALUE VALUES
    (1, 1, 2, 12, NULL),
    (2, 1, 1, 15, NULL),
    (3, 2, 1, 5, 100),
    (4, 2, 2, 5, 102.5),
    (5, 3, 1, 10, 20),
    (6, 4, 1, 60, NULL);

INSERT INTO programs (id, name, description, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Strength', 'Three sessions a week.', '{"ru": {"name": "Сила", "description": "Три тренировки в неделю."}}'),
    (2, 'Cardio', NULL, '{}');

-- Full Body A twice, assigned out of weekday order.
INSERT INTO program_workout_template (program_id, template_id, weekday) VALUES
    (1, 1, 'thursday'),
    (1, 2, 'wednesday'),
    (1, 1, 'monday');

-- Rows a test adds get the next ids.
SELECT setval(pg_get_serial_sequence('equipment', 'id'), 3);
SELECT setval(pg_get_serial_sequence('categories', 'id'), 3);
SELECT setval(pg_get_serial_sequence('exercises', 'id'), 7);
SELECT setval(pg_get_serial_sequence('workout_templates', 'id'), 2);
SELECT setval(pg_get_serial_sequence('template_activities', 'id'), 4);
SELECT setval(pg_get_serial_sequence('template_sets', 'id'), 6);
SELECT setval(pg_get_serial_sequence('programs', 'id'), 2);

-- A small catalog for the workout tests: a weight, a heart-rate-zone and a
-- no-difficulty exercise, a retired one, and two templates.

INSERT INTO equipment (id, name, difficulty_unit, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Barbell', 'kilograms', '{"ru": {"name": "Штанга"}}'),
    (2, 'Indoor Bike', 'heart_rate_zone', '{"ru": {"name": "Велотренажёр"}}'),
    (3, 'Bodyweight', 'none', '{}');

INSERT INTO categories (id, name, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Chest', '{"ru": {"name": "Грудь"}}'),
    (2, 'Cardio', '{}');

INSERT INTO exercises (id, name, equipment_id, effort_type, rest_time_seconds, translations, deleted_at) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Bench Press', 1, 'repetitions', 120, '{"ru": {"name": "Жим лёжа"}}', NULL),
    (2, 'Easy Ride', 2, 'duration', NULL, '{"ru": {"name": "Лёгкий заезд"}}', NULL),
    (3, 'Push-up', 3, 'repetitions', 60, '{}', NULL),
    (4, 'Upright Row', 1, 'repetitions', 90, '{}', '2026-10-01 00:00:00+00');

INSERT INTO category_exercise (exercise_id, category_id) VALUES (1, 1), (2, 2), (3, 1), (4, 1);

INSERT INTO workout_templates (id, name, description, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Full Body', 'Press and push-ups.', '{"ru": {"name": "Всё тело", "description": "Жим и отжимания."}}'),
    (2, 'Ride', NULL, '{}');

INSERT INTO template_activities (id, template_id, exercise_id, "order") OVERRIDING SYSTEM VALUE VALUES
    (1, 1, 1, 1),
    (2, 1, 3, 2),
    (3, 2, 2, 1);

INSERT INTO template_sets (id, activity_id, "order", effort_value, difficulty_value) OVERRIDING SYSTEM VALUE VALUES
    (1, 1, 1, 10, 20),
    (2, 1, 2, 8, 22.5),
    (3, 2, 1, 15, NULL),
    (4, 3, 1, 1800, 2);

SELECT setval(pg_get_serial_sequence('equipment', 'id'), 3);
SELECT setval(pg_get_serial_sequence('categories', 'id'), 2);
SELECT setval(pg_get_serial_sequence('exercises', 'id'), 4);
SELECT setval(pg_get_serial_sequence('workout_templates', 'id'), 2);
SELECT setval(pg_get_serial_sequence('template_activities', 'id'), 3);
SELECT setval(pg_get_serial_sequence('template_sets', 'id'), 4);

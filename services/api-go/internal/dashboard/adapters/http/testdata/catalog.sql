-- Programs for the dashboard tests: two share Full Body on Monday, and one
-- schedules nothing.

INSERT INTO workout_templates (id, name, description, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Full Body', 'Press and push-ups.', '{"ru": {"name": "Всё тело", "description": "Жим и отжимания."}}'),
    (2, 'Ride', NULL, '{}');

INSERT INTO programs (id, name, translations) OVERRIDING SYSTEM VALUE VALUES
    (1, 'Strength', '{"ru": {"name": "Сила"}}'),
    (2, 'Cardio', '{}'),
    (3, 'Rest', '{}');

INSERT INTO program_workout_template (program_id, template_id, weekday) VALUES
    (1, 1, 'monday'),
    (1, 2, 'wednesday'),
    (2, 1, 'monday'),
    (2, 2, 'sunday');

SELECT setval(pg_get_serial_sequence('workout_templates', 'id'), 2);
SELECT setval(pg_get_serial_sequence('programs', 'id'), 3);

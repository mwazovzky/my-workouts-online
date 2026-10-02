# Admin Content Management

App admins create, edit, and delete the shared content catalog: equipment, categories, exercises, workout templates, and programs.

## Current Behavior

1. A user with the `Admin` role sees an **Admin** link in the desktop and mobile navigation. Non-admins never see it.
2. Admin opens **Admin/Index** and picks a section: Equipment, Categories, Exercises, Workout templates, or Programs.
3. Each section page lists existing records with **Edit** and **Delete** actions and a **New** button.
4. Creating or editing navigates to a dedicated form page (e.g. `admin.categories.create`, `admin.categories.edit`), matching the app's page-based editing pattern (like `WorkoutEdit`). Edit pages load the record by id from the REST API on mount (showing a skeleton), so they work on refresh and deep-link. Text fields are captured per locale — English (required) and Russian (optional).
5. The workout-template form is a nested builder: add activities (each picks an exercise), and add sets under each activity (effort value plus a difficulty field whose type follows the exercise's equipment — a number for weights, a 1–5 picker for heart-rate zones, nothing for bodyweight).
6. The program form edits translations plus a weekly schedule: each row assigns a workout template to a weekday (the same template may be scheduled on more than one weekday).
7. On save the record is persisted through the REST API and the admin returns to the section list with a success toast.
8. Deleting prompts a confirmation dialog before removing the record.

## Business Rules

- All admin actions require the authenticated user to have the `Admin` role. Enforced by the `admin` route middleware and by a per-model policy (`EquipmentPolicy`, `CategoryPolicy`, `ExercisePolicy`, `WorkoutTemplatePolicy`, `ProgramPolicy`); non-admins receive `403`.
- Content is bilingual. Each translatable field accepts an English value (required) and a Russian value (optional). A blank Russian value is removed so reads fall back to the English source text.
- **Equipment** carries a `difficulty_unit` (kilograms, pounds, plates, none) and a translatable `name`. Equipment referenced by any exercise cannot be deleted; the API returns `409`.
- **Category** carries a translatable `name`. Deleting a category detaches it from its exercises.
- **Exercise** carries `equipment_id`, `effort_type` (repetitions, duration), optional `rest_time_seconds`, a set of categories, and translatable `name` + `description`. Category membership is synced on every save. Deleting an exercise **retires it (soft delete)**: it leaves the catalog and pickers, but templates and users' logged workouts keep it and still show its name. Equipment referenced by a retired exercise still can't be deleted (409).
- **Workout template** carries translatable `name` + `description` and an ordered list of activities, each an exercise with one or more sets (`effort_value`, optional `difficulty_value`). At least one activity, and at least one set per activity, are required. A set's `difficulty_value` is validated against the exercise's difficulty unit — heart-rate-zone exercises accept 1–5 (via `HeartRateZoneWithinRange`). Orders are 1–1000, `effort_value` is 0–100000 and `difficulty_value` is 0–10000, so values can never exceed the database columns. Updating a template replaces its activities and sets wholesale.
- **Program** carries translatable `name` + `description` and a set of weekday assignments (`{workout_template_id, weekday}`), stored on the `program_workout_template` pivot. Assignments are optional; the same template may be scheduled on multiple weekdays. Updating a program replaces its assignments wholesale. Enrollment (`program_user`) is unaffected by admin edits, but **deleting a program un-enrolls its users** (their logged workouts remain intact). A workout template can't be deleted while a program uses it (409).
- Write operations run in a database transaction; exercise category sync, template activities/sets, program assignments, and translation writes commit together.
- **Shipped records are owned by the repository.** Records defined in `database/catalog/` are reset to those files whenever `catalog:sync` runs, so an admin edit to one of them (including retiring a shipped exercise) is overwritten; change the data file instead. Records created in the admin UI are never touched by the sync. See [Architecture](../architecture.md) (Catalog Sync).

## Known Limitations

- Programs support only a single weekly schedule — no multi-week periodization.
- No search, filter, sort, or pagination on the admin list pages.
- No image or media management for exercises.
- Admin editing covers English and Russian only, matching the app's supported locales. The API accepts every locale in `available_locales`; the admin forms still list English and Russian explicitly.
- No audit log of admin changes.

## Surface Area

- Index pages: `Admin/Index`, `Admin/EquipmentIndex`, `Admin/CategoryIndex`, `Admin/ExerciseIndex`, `Admin/WorkoutTemplateIndex`, `Admin/ProgramIndex`
- Create/Edit pages: `Admin/{Equipment,Category,Exercise,WorkoutTemplate,Program}{Create,Edit}` (each hosts the shared `Components/Admin/*Form` component)
- Web route names: `admin.index`, `admin.equipment`, `admin.categories`, `admin.exercises`, `admin.workout-templates`, `admin.programs`, plus `admin.{resource}.create` and `admin.{resource}.edit` for each resource
- API route names: `api.v1.admin.equipment.*`, `api.v1.admin.categories.*`, `api.v1.admin.exercises.*`, `api.v1.admin.workout-templates.*`, `api.v1.admin.programs.*` — all now expose `index`, `show`, `store`, `update`, `destroy`
- Full reference: [Pages & Routes](../pages-and-routes.md)

## Data Notes

Admin payloads send and return every locale under `translations.{locale}`; these per-locale values are not part of the public catalog responses. How the values are stored is described in [Architecture](../architecture.md#internationalization).

## Related

- [Programs](programs.md)
- [Architecture](../architecture.md)
- [Product Overview](../product.md)

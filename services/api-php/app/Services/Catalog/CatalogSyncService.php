<?php

namespace App\Services\Catalog;

use App\Enums\DifficultyUnit;
use App\Exceptions\InvalidCatalogException;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use App\Services\Admin\CategoryServiceInterface;
use App\Services\Admin\EquipmentServiceInterface;
use App\Services\Admin\ExerciseServiceInterface;
use App\Services\Admin\ProgramServiceInterface;
use App\Services\Admin\WorkoutTemplateServiceInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class CatalogSyncService implements CatalogSyncServiceInterface
{
    private const MODELS = [
        'equipment' => Equipment::class,
        'categories' => Category::class,
        'exercises' => Exercise::class,
        'workout_templates' => WorkoutTemplate::class,
        'programs' => Program::class,
    ];

    /**
     * Existing rows per type: managed rows by slug, unmanaged rows by English name.
     *
     * @var array<string, array{slugs: array<string, Model>, names: array<string, array<int, Model>>}>
     */
    private array $index = [];

    /**
     * Ids of the synced records per type, by slug.
     *
     * @var array<string, array<string, int>>
     */
    private array $ids = [];

    private CatalogSyncReport $report;

    public function __construct(
        private readonly CatalogLoader $loader,
        private readonly CatalogValidator $validator,
        private readonly EquipmentServiceInterface $equipmentService,
        private readonly CategoryServiceInterface $categoryService,
        private readonly ExerciseServiceInterface $exerciseService,
        private readonly WorkoutTemplateServiceInterface $workoutTemplateService,
        private readonly ProgramServiceInterface $programService,
    ) {}

    public function sync(bool $dryRun = false, bool $strict = false): CatalogSyncReport
    {
        $catalog = $this->loader->load();
        $violations = $this->validator->validate($catalog);

        if ($violations !== []) {
            throw new InvalidCatalogException($violations);
        }

        $this->report = new CatalogSyncReport($dryRun);
        $this->index = [];
        $this->ids = [];

        DB::beginTransaction();

        try {
            foreach (self::MODELS as $type => $modelClass) {
                $this->index[$type] = $this->indexExistingRows($modelClass);
            }

            $this->syncEquipment($catalog['equipment']);
            $this->syncCategories($catalog['categories']);
            $this->syncExercises($catalog['exercises'], $catalog['equipment']);
            $this->syncWorkoutTemplates($catalog['workout_templates']);
            $this->syncPrograms($catalog['programs']);
            $this->retire($catalog['retired']);

            $commit = ! $dryRun && ! ($strict && $this->report->hasWarnings());

            if ($commit) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            $this->report->markCommitted($commit);
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return $this->report;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function syncEquipment(array $records): void
    {
        foreach ($records as $record) {
            $payload = [
                'difficulty_unit' => $record['difficulty_unit']->value,
                'translations' => $record['translations'],
            ];

            $existing = $this->find('equipment', $record['slug'], $this->englishNames($record));

            if ($existing instanceof Equipment
                && $existing->difficulty_unit !== $record['difficulty_unit']
                && $this->hasLoggedActivities($existing->exercises()->withTrashed()->pluck('id')->all())
            ) {
                $this->report->warn("equipment [{$record['slug']}]: difficulty unit changes from {$existing->difficulty_unit->value} to {$record['difficulty_unit']->value}, which relabels logged workouts.");
            }

            $this->persist(
                'equipment',
                $record['slug'],
                $existing,
                ['difficulty_unit' => $payload['difficulty_unit']] + $this->flattenTranslations($record['translations']),
                fn (Equipment $equipment) => ['difficulty_unit' => $equipment->difficulty_unit->value]
                    + $this->currentTranslations($equipment, $record['translations']),
                fn () => $this->equipmentService->create($payload),
                fn (Equipment $equipment) => $this->equipmentService->update($equipment, $payload),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function syncCategories(array $records): void
    {
        foreach ($records as $record) {
            $payload = ['translations' => $record['translations']];

            $this->persist(
                'categories',
                $record['slug'],
                $this->find('categories', $record['slug'], $this->englishNames($record)),
                $this->flattenTranslations($record['translations']),
                fn (Category $category) => $this->currentTranslations($category, $record['translations']),
                fn () => $this->categoryService->create($payload),
                fn (Category $category) => $this->categoryService->update($category, $payload),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $equipmentRecords
     */
    private function syncExercises(array $records, array $equipmentRecords): void
    {
        $units = array_column($equipmentRecords, 'difficulty_unit', 'slug');

        foreach ($records as $record) {
            $categoryIds = array_map(
                fn (string $slug) => $this->idOf('categories', $slug),
                $record['categories'],
            );
            sort($categoryIds);

            $payload = [
                'equipment_id' => $this->idOf('equipment', $record['equipment']),
                'effort_type' => $record['effort_type']->value,
                'rest_time_seconds' => $record['rest_time_seconds'],
                'category_ids' => $categoryIds,
                'translations' => $record['translations'],
            ];

            $existing = $this->find('exercises', $record['slug'], $this->englishNames($record));

            if ($existing instanceof Exercise) {
                $this->warnWhenMeasurementChanges($existing, $record, $units[$record['equipment']]);
            }

            $this->persist(
                'exercises',
                $record['slug'],
                $existing,
                [
                    'equipment' => $payload['equipment_id'],
                    'effort_type' => $payload['effort_type'],
                    'rest_time_seconds' => $payload['rest_time_seconds'],
                    'categories' => $categoryIds,
                ] + $this->flattenTranslations($record['translations']),
                fn (Exercise $exercise) => [
                    'equipment' => $exercise->equipment_id,
                    'effort_type' => $exercise->effort_type->value,
                    'rest_time_seconds' => $exercise->rest_time_seconds,
                    'categories' => $exercise->categories()->orderBy('categories.id')->pluck('categories.id')
                        ->map(fn (mixed $id) => (int) $id)
                        ->all(),
                ] + $this->currentTranslations($exercise, $record['translations']),
                fn () => $this->exerciseService->create($payload),
                fn (Exercise $exercise) => $this->exerciseService->update($exercise, $payload),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function syncWorkoutTemplates(array $records): void
    {
        foreach ($records as $record) {
            $activities = [];

            foreach ($record['activities'] as $activityIndex => $activity) {
                $sets = [];

                foreach ($activity['sets'] as $setIndex => $set) {
                    $sets[] = [
                        'order' => $setIndex + 1,
                        'effort_value' => $set['effort'],
                        'difficulty_value' => $set['difficulty'] === null ? null : (float) $set['difficulty'],
                    ];
                }

                $activities[] = [
                    'exercise_id' => $this->idOf('exercises', $activity['exercise']),
                    'order' => $activityIndex + 1,
                    'sets' => $sets,
                ];
            }

            $payload = ['translations' => $record['translations'], 'activities' => $activities];

            $this->persist(
                'workout_templates',
                $record['slug'],
                $this->find('workout_templates', $record['slug'], $this->englishNames($record)),
                ['activities' => $activities] + $this->flattenTranslations($record['translations']),
                fn (WorkoutTemplate $template) => ['activities' => $this->currentActivities($template)]
                    + $this->currentTranslations($template, $record['translations']),
                fn () => $this->workoutTemplateService->create($payload),
                fn (WorkoutTemplate $template) => $this->workoutTemplateService->update($template, $payload),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function syncPrograms(array $records): void
    {
        foreach ($records as $record) {
            $assignments = array_map(
                fn (array $entry) => [
                    'workout_template_id' => $this->idOf('workout_templates', $entry['template']),
                    'weekday' => $entry['weekday']->value,
                ],
                array_values($record['schedule']),
            );

            $payload = ['translations' => $record['translations'], 'assignments' => $assignments];

            $this->persist(
                'programs',
                $record['slug'],
                $this->find('programs', $record['slug'], $this->englishNames($record)),
                ['schedule' => $assignments] + $this->flattenTranslations($record['translations']),
                fn (Program $program) => ['schedule' => $this->currentAssignments($program)]
                    + $this->currentTranslations($program, $record['translations']),
                fn () => $this->programService->create($payload),
                fn (Program $program) => $this->programService->update($program, $payload),
            );
        }
    }

    /**
     * Create the record, or bring the existing row in line with the data files.
     *
     * @template TModel of Model
     *
     * @param  TModel|null  $existing
     * @param  array<string, mixed>  $desired  Desired state by field
     * @param  Closure(TModel): array<string, mixed>  $currentState  Current state by field, same shape as $desired
     * @param  Closure(): TModel  $create
     * @param  Closure(TModel): TModel  $update
     */
    private function persist(
        string $type,
        string $slug,
        ?Model $existing,
        array $desired,
        Closure $currentState,
        Closure $create,
        Closure $update,
    ): void {
        if ($existing === null) {
            $model = $create();
            $this->assignSlug($model, $slug);
            $this->remember($type, $slug, $model);
            $this->report->record($type, CatalogSyncReport::CREATED, $slug);

            return;
        }

        $changedFields = $this->changedFields($currentState($existing), $desired);

        if ($this->isTrashed($existing) && method_exists($existing, 'restore')) {
            $existing->restore();
            $changedFields[] = 'restored';
        }

        if ($changedFields !== []) {
            $update($existing);
        }

        $adopted = $existing->getAttribute('slug') === null;

        if ($adopted) {
            $this->assignSlug($existing, $slug);
        }

        $this->remember($type, $slug, $existing);

        $outcome = match (true) {
            $adopted => CatalogSyncReport::ADOPTED,
            $changedFields !== [] => CatalogSyncReport::UPDATED,
            default => CatalogSyncReport::UNCHANGED,
        };

        $this->report->record($type, $outcome, $slug, $changedFields);
    }

    /**
     * Remove records listed as retired. Runs in reverse dependency order so
     * a retired program releases its templates before they are checked.
     *
     * @param  array<string, array<int, array{slug: string, names: array<int, string>}>>  $retired
     */
    private function retire(array $retired): void
    {
        foreach (array_reverse(CatalogLoader::TYPES) as $type) {
            foreach ($retired[$type] ?? [] as $entry) {
                $model = $this->find($type, $entry['slug'], $entry['names']);

                if ($model === null) {
                    continue;
                }

                $blocker = $this->retirementBlocker($model);

                if ($blocker !== null) {
                    $this->report->warn("{$type} [{$entry['slug']}]: not retired because {$blocker}.");

                    continue;
                }

                if ($model instanceof Exercise) {
                    if ($model->getAttribute('slug') === null) {
                        $this->assignSlug($model, $entry['slug']);
                    }

                    if ($model->trashed()) {
                        continue;
                    }
                }

                $this->delete($model);
                $this->report->record($type, CatalogSyncReport::RETIRED, $entry['slug']);
            }
        }
    }

    private function retirementBlocker(Model $model): ?string
    {
        return match (true) {
            $model instanceof Equipment && $model->exercises()->withTrashed()->exists() => 'exercises still use it',
            $model instanceof Category && $model->exercises()->withTrashed()->exists() => 'exercises still belong to it',
            $model instanceof WorkoutTemplate && $model->programs()->exists() => 'a program still uses it',
            $model instanceof Program && $model->users()->exists() => 'users are enrolled in it',
            default => null,
        };
    }

    private function delete(Model $model): void
    {
        match (true) {
            $model instanceof Equipment => $this->equipmentService->delete($model),
            $model instanceof Category => $this->categoryService->delete($model),
            $model instanceof Exercise => $this->exerciseService->delete($model),
            $model instanceof WorkoutTemplate => $this->workoutTemplateService->delete($model),
            $model instanceof Program => $this->programService->delete($model),
            default => throw new LogicException('Unsupported catalog model '.$model::class),
        };
    }

    /**
     * @param  class-string<Category|Equipment|Exercise|Program|WorkoutTemplate>  $modelClass
     * @return array{slugs: array<string, Model>, names: array<string, array<int, Model>>}
     */
    private function indexExistingRows(string $modelClass): array
    {
        $query = $modelClass::query()->withoutGlobalScope(SoftDeletingScope::class)->orderBy('id');

        $index = ['slugs' => [], 'names' => []];

        foreach ($query->get() as $model) {
            $slug = $model->getAttribute('slug');

            if ($slug !== null) {
                $index['slugs'][$slug] = $model;

                continue;
            }

            $name = $this->normaliseName((string) $model->rawTranslation('name', config('app.source_locale')));

            if ($name !== '') {
                $index['names'][$name][$model->getKey()] = $model;
            }
        }

        return $index;
    }

    /**
     * Find the row of a record: by slug, otherwise an unmanaged row carrying
     * one of the given English names. A row can be claimed only once.
     *
     * @param  array<int, string>  $names
     * @return ($type is 'equipment' ? Equipment|null : ($type is 'categories' ? Category|null : ($type is 'exercises' ? Exercise|null : ($type is 'workout_templates' ? WorkoutTemplate|null : ($type is 'programs' ? Program|null : Model|null)))))
     */
    private function find(string $type, string $slug, array $names): ?Model
    {
        if (isset($this->index[$type]['slugs'][$slug])) {
            return $this->index[$type]['slugs'][$slug];
        }

        $candidates = [];

        foreach ($names as $name) {
            $candidates += $this->index[$type]['names'][$this->normaliseName($name)] ?? [];
        }

        if ($candidates === []) {
            return null;
        }

        ksort($candidates);

        $active = array_filter($candidates, fn (Model $model) => ! $this->isTrashed($model));
        $chosen = reset($active) ?: reset($candidates);

        if (count($candidates) > 1) {
            $this->report->warn(sprintf(
                '%s [%s]: %d rows match by name (ids %s); using id %d.',
                $type,
                $slug,
                count($candidates),
                implode(', ', array_keys($candidates)),
                $chosen->getKey(),
            ));
        }

        foreach (array_keys($this->index[$type]['names']) as $name) {
            unset($this->index[$type]['names'][$name][$chosen->getKey()]);
        }

        $this->index[$type]['slugs'][$slug] = $chosen;

        return $chosen;
    }

    private function remember(string $type, string $slug, Model $model): void
    {
        $this->index[$type]['slugs'][$slug] = $model;
        $this->ids[$type][$slug] = $model->getKey();
    }

    /**
     * @throws InvalidCatalogException
     */
    private function idOf(string $type, string $slug): int
    {
        return $this->ids[$type][$slug]
            ?? throw new InvalidCatalogException(["{$type} [{$slug}] is referenced but was not synced."]);
    }

    private function assignSlug(Model $model, string $slug): void
    {
        $model->forceFill(['slug' => $slug])->save();
    }

    /**
     * Stored difficulty and effort values carry no unit, so changing how an
     * exercise is measured silently changes the meaning of logged workouts.
     *
     * @param  array<string, mixed>  $record
     */
    private function warnWhenMeasurementChanges(Exercise $exercise, array $record, DifficultyUnit $unit): void
    {
        $changes = [];

        if ($exercise->effort_type !== $record['effort_type']) {
            $changes[] = "effort type changes from {$exercise->effort_type->value} to {$record['effort_type']->value}";
        }

        $currentUnit = $exercise->equipment()->first()?->difficulty_unit;

        if ($exercise->equipment_id !== $this->idOf('equipment', $record['equipment']) && $currentUnit !== $unit) {
            $changes[] = "difficulty unit changes from {$currentUnit?->value} to {$unit->value}";
        }

        if ($changes === [] || ! $this->hasLoggedActivities([$exercise->getKey()])) {
            return;
        }

        $this->report->warn("exercises [{$record['slug']}]: ".implode(' and ', $changes).', which relabels logged workouts.');
    }

    /**
     * @param  array<int, int>  $exerciseIds
     */
    private function hasLoggedActivities(array $exerciseIds): bool
    {
        if ($exerciseIds === []) {
            return false;
        }

        return Activity::query()
            ->where('workout_type', (new Workout)->getMorphClass())
            ->whereIn('exercise_id', $exerciseIds)
            ->exists();
    }

    /**
     * @return array<int, array{exercise_id: int, order: int, sets: array<int, array{order: int, effort_value: int, difficulty_value: ?float}>}>
     */
    private function currentActivities(WorkoutTemplate $template): array
    {
        return $template->activities()
            ->orderBy('order')
            ->with(['sets' => fn ($query) => $query->orderBy('order')])
            ->get()
            ->map(fn (Activity $activity) => [
                'exercise_id' => $activity->exercise_id,
                'order' => $activity->order,
                'sets' => $activity->sets->map(fn ($set) => [
                    'order' => $set->order,
                    'effort_value' => $set->effort_value,
                    'difficulty_value' => $set->difficulty_value,
                ])->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{workout_template_id: int, weekday: string}>
     */
    private function currentAssignments(Program $program): array
    {
        return $program->workoutTemplates()
            ->orderBy('program_workout_template.id')
            ->get()
            ->map(fn (WorkoutTemplate $template) => [
                'workout_template_id' => $template->getKey(),
                'weekday' => $template->pivot->weekday,
            ])
            ->all();
    }

    /**
     * @param  array<string, array<string, string>>  $translations
     * @return array<string, string>
     */
    private function flattenTranslations(array $translations): array
    {
        $flat = [];

        foreach ($translations as $locale => $fields) {
            foreach ($fields as $field => $value) {
                $flat["{$field} ({$locale})"] = $value;
            }
        }

        return $flat;
    }

    /**
     * Stored values for the locales and fields the data files define.
     *
     * @param  array<string, array<string, string>>  $translations
     * @return array<string, ?string>
     */
    private function currentTranslations(Category|Equipment|Exercise|Program|WorkoutTemplate $model, array $translations): array
    {
        $flat = [];

        foreach ($translations as $locale => $fields) {
            foreach (array_keys($fields) as $field) {
                $flat["{$field} ({$locale})"] = $model->rawTranslation($field, $locale);
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $desired
     * @return array<int, string>
     */
    private function changedFields(array $current, array $desired): array
    {
        $changed = [];

        foreach ($desired as $field => $value) {
            if (($current[$field] ?? null) !== $value) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, string>
     */
    private function englishNames(array $record): array
    {
        return [$record['translations'][config('app.source_locale')]['name'], ...$record['previous_names']];
    }

    private function isTrashed(Model $model): bool
    {
        return method_exists($model, 'trashed') && $model->trashed();
    }

    private function normaliseName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}

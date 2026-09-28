<?php

namespace App\Services\Catalog;

use App\Enums\DifficultyUnit;
use App\Enums\EffortType;
use App\Enums\Weekday;

class CatalogValidator
{
    private const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const SLUG_MAX_LENGTH = 100;

    private const FIELD_MAX_LENGTH = ['name' => 255, 'description' => 2000];

    private const MAX_REST_TIME_SECONDS = 3600;

    private const TRANSLATABLE_FIELDS = [
        'equipment' => ['name'],
        'categories' => ['name'],
        'exercises' => ['name', 'description'],
        'workout_templates' => ['name', 'description'],
        'programs' => ['name', 'description'],
    ];

    /**
     * @var array<int, string>
     */
    private array $violations = [];

    /**
     * @param  array<int, string>  $locales
     */
    public function __construct(private readonly array $locales) {}

    /**
     * Check the catalog definition without touching the database.
     *
     * @param  array<string, mixed>  $catalog  As returned by CatalogLoader::load()
     * @return array<int, string> Violations; empty when the catalog is valid
     */
    public function validate(array $catalog): array
    {
        $this->violations = [];

        foreach (CatalogLoader::TYPES as $type) {
            $this->validateCommonFields($type, $catalog[$type] ?? []);
        }

        $equipmentUnits = $this->validateEquipment($catalog['equipment'] ?? []);
        $exerciseUnits = $this->validateExercises(
            $catalog['exercises'] ?? [],
            $equipmentUnits,
            $this->slugs($catalog['categories'] ?? []),
        );

        $this->validateWorkoutTemplates($catalog['workout_templates'] ?? [], $exerciseUnits);
        $this->validatePrograms($catalog['programs'] ?? [], $this->slugs($catalog['workout_templates'] ?? []));
        $this->validateRetired($catalog);

        return $this->violations;
    }

    /**
     * @param  array<int, mixed>  $records
     */
    private function validateCommonFields(string $type, array $records): void
    {
        $seenSlugs = [];
        $seenNames = [];

        foreach ($records as $index => $record) {
            $label = $this->label($type, $record, $index);

            if (! is_array($record)) {
                $this->violations[] = "{$label}: record must be an array.";

                continue;
            }

            $this->validateSlug($label, $record['slug'] ?? null, $seenSlugs);
            $this->validateTranslations($type, $label, $record['translations'] ?? null);
            $this->validatePreviousNames($label, $record['previous_names'] ?? []);

            foreach ($this->namesByLocale($record) as $locale => $names) {
                foreach ($names as $name) {
                    $key = $locale.'|'.$this->normaliseName($name);

                    if (isset($seenNames[$key]) && $seenNames[$key] !== $label) {
                        $this->violations[] = "{$label}: name \"{$name}\" ({$locale}) is already used by {$seenNames[$key]}.";

                        continue;
                    }

                    $seenNames[$key] = $label;
                }
            }
        }
    }

    /**
     * @param  array<string, true>  $seenSlugs
     */
    private function validateSlug(string $label, mixed $slug, array &$seenSlugs): void
    {
        if (! is_string($slug) || $slug === '') {
            $this->violations[] = "{$label}: slug is required.";

            return;
        }

        if (strlen($slug) > self::SLUG_MAX_LENGTH || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            $this->violations[] = "{$label}: slug must be lowercase words separated by hyphens, up to ".self::SLUG_MAX_LENGTH.' characters.';
        }

        if (isset($seenSlugs[$slug])) {
            $this->violations[] = "{$label}: slug is used more than once.";
        }

        $seenSlugs[$slug] = true;
    }

    private function validateTranslations(string $type, string $label, mixed $translations): void
    {
        if (! is_array($translations)) {
            $this->violations[] = "{$label}: translations are required.";

            return;
        }

        $fields = self::TRANSLATABLE_FIELDS[$type];

        foreach (array_diff(array_keys($translations), $this->locales) as $locale) {
            $this->violations[] = "{$label}: locale \"{$locale}\" is not supported.";
        }

        foreach ($this->locales as $locale) {
            $values = is_array($translations[$locale] ?? null) ? $translations[$locale] : [];

            foreach (array_diff(array_keys($values), $fields) as $field) {
                $this->violations[] = "{$label}: field \"{$field}\" ({$locale}) is not translatable.";
            }

            foreach ($fields as $field) {
                $value = $values[$field] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    $this->violations[] = "{$label}: {$field} ({$locale}) is required.";

                    continue;
                }

                if (mb_strlen($value) > self::FIELD_MAX_LENGTH[$field]) {
                    $this->violations[] = "{$label}: {$field} ({$locale}) is longer than ".self::FIELD_MAX_LENGTH[$field].' characters.';
                }
            }
        }
    }

    private function validatePreviousNames(string $label, mixed $previousNames): void
    {
        if (! is_array($previousNames)) {
            $this->violations[] = "{$label}: previous_names must be a list.";

            return;
        }

        foreach ($previousNames as $name) {
            if (! is_string($name) || trim($name) === '') {
                $this->violations[] = "{$label}: previous_names must contain non-empty strings.";
            }
        }
    }

    /**
     * @param  array<int, mixed>  $records
     * @return array<string, DifficultyUnit> Difficulty unit by equipment slug
     */
    private function validateEquipment(array $records): array
    {
        $units = [];

        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                continue;
            }

            $unit = $record['difficulty_unit'] ?? null;

            if (! $unit instanceof DifficultyUnit) {
                $this->violations[] = $this->label('equipment', $record, $index).': difficulty_unit must be a DifficultyUnit case.';

                continue;
            }

            if (is_string($record['slug'] ?? null)) {
                $units[$record['slug']] = $unit;
            }
        }

        return $units;
    }

    /**
     * @param  array<int, mixed>  $records
     * @param  array<string, DifficultyUnit>  $equipmentUnits
     * @param  array<int, string>  $categorySlugs
     * @return array<string, ?DifficultyUnit> Difficulty unit by exercise slug
     */
    private function validateExercises(array $records, array $equipmentUnits, array $categorySlugs): array
    {
        $units = [];

        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                continue;
            }

            $label = $this->label('exercises', $record, $index);
            $equipment = $record['equipment'] ?? null;

            if (! is_string($equipment) || ! isset($equipmentUnits[$equipment])) {
                $this->violations[] = "{$label}: equipment \"".$this->printable($equipment).'" does not exist.';
            }

            if (! ($record['effort_type'] ?? null) instanceof EffortType) {
                $this->violations[] = "{$label}: effort_type must be an EffortType case.";
            }

            $restTime = $record['rest_time_seconds'] ?? null;

            if ($restTime !== null && (! is_int($restTime) || $restTime < 0 || $restTime > self::MAX_REST_TIME_SECONDS)) {
                $this->violations[] = "{$label}: rest_time_seconds must be null or an integer from 0 to ".self::MAX_REST_TIME_SECONDS.'.';
            }

            $this->validateCategories($label, $record['categories'] ?? null, $categorySlugs);

            if (is_string($record['slug'] ?? null)) {
                $units[$record['slug']] = is_string($equipment) ? ($equipmentUnits[$equipment] ?? null) : null;
            }
        }

        return $units;
    }

    /**
     * @param  array<int, string>  $categorySlugs
     */
    private function validateCategories(string $label, mixed $categories, array $categorySlugs): void
    {
        if (! is_array($categories) || $categories === []) {
            $this->violations[] = "{$label}: at least one category is required.";

            return;
        }

        foreach ($categories as $category) {
            if (! is_string($category) || ! in_array($category, $categorySlugs, true)) {
                $this->violations[] = "{$label}: category \"".$this->printable($category).'" does not exist.';
            }
        }

        if (count($categories) !== count(array_unique($categories, SORT_REGULAR))) {
            $this->violations[] = "{$label}: categories must not repeat.";
        }
    }

    /**
     * @param  array<int, mixed>  $records
     * @param  array<string, ?DifficultyUnit>  $exerciseUnits
     */
    private function validateWorkoutTemplates(array $records, array $exerciseUnits): void
    {
        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                continue;
            }

            $label = $this->label('workout_templates', $record, $index);
            $activities = $record['activities'] ?? null;

            if (! is_array($activities) || $activities === []) {
                $this->violations[] = "{$label}: at least one activity is required.";

                continue;
            }

            foreach (array_values($activities) as $position => $activity) {
                $activityLabel = "{$label} activity ".($position + 1);
                $exercise = is_array($activity) ? ($activity['exercise'] ?? null) : null;

                if (! is_string($exercise) || ! array_key_exists($exercise, $exerciseUnits)) {
                    $this->violations[] = "{$activityLabel}: exercise \"".$this->printable($exercise).'" does not exist.';

                    continue;
                }

                $this->validateSets($activityLabel, $activity['sets'] ?? null, $exerciseUnits[$exercise]);
            }
        }
    }

    private function validateSets(string $label, mixed $sets, ?DifficultyUnit $unit): void
    {
        if (! is_array($sets) || $sets === []) {
            $this->violations[] = "{$label}: at least one set is required.";

            return;
        }

        foreach (array_values($sets) as $position => $set) {
            $setLabel = "{$label} set ".($position + 1);
            $effort = is_array($set) ? ($set['effort'] ?? null) : null;
            $difficulty = is_array($set) ? ($set['difficulty'] ?? null) : null;

            if (! is_int($effort) || $effort < 1) {
                $this->violations[] = "{$setLabel}: effort must be a positive integer.";
            }

            if ($difficulty === null) {
                continue;
            }

            if ((! is_int($difficulty) && ! is_float($difficulty)) || $difficulty < 0) {
                $this->violations[] = "{$setLabel}: difficulty must be null or a non-negative number.";

                continue;
            }

            if ($unit === DifficultyUnit::None) {
                $this->violations[] = "{$setLabel}: difficulty must be null because the equipment has no difficulty unit.";
            }

            if ($unit === DifficultyUnit::HeartRateZone && (! is_int($difficulty) || $difficulty < 1 || $difficulty > 5)) {
                $this->violations[] = "{$setLabel}: heart-rate zone must be an integer from 1 to 5.";
            }
        }
    }

    /**
     * @param  array<int, mixed>  $records
     * @param  array<int, string>  $templateSlugs
     */
    private function validatePrograms(array $records, array $templateSlugs): void
    {
        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                continue;
            }

            $label = $this->label('programs', $record, $index);
            $schedule = $record['schedule'] ?? null;

            if (! is_array($schedule) || $schedule === []) {
                $this->violations[] = "{$label}: at least one scheduled workout is required.";

                continue;
            }

            $seen = [];

            foreach (array_values($schedule) as $position => $entry) {
                $entryLabel = "{$label} schedule entry ".($position + 1);
                $weekday = is_array($entry) ? ($entry['weekday'] ?? null) : null;
                $template = is_array($entry) ? ($entry['template'] ?? null) : null;

                if (! $weekday instanceof Weekday) {
                    $this->violations[] = "{$entryLabel}: weekday must be a Weekday case.";
                }

                if (! is_string($template) || ! in_array($template, $templateSlugs, true)) {
                    $this->violations[] = "{$entryLabel}: workout template \"".$this->printable($template).'" does not exist.';

                    continue;
                }

                if ($weekday instanceof Weekday) {
                    $key = $weekday->value.'|'.$template;

                    if (isset($seen[$key])) {
                        $this->violations[] = "{$entryLabel}: workout template \"{$template}\" is already scheduled on {$weekday->value}.";
                    }

                    $seen[$key] = true;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function validateRetired(array $catalog): void
    {
        $retired = is_array($catalog['retired'] ?? null) ? $catalog['retired'] : [];

        foreach (array_diff(array_keys($retired), CatalogLoader::TYPES) as $type) {
            $this->violations[] = "retired: \"{$type}\" is not a catalog type.";
        }

        foreach (CatalogLoader::TYPES as $type) {
            $activeSlugs = $this->slugs($catalog[$type] ?? []);
            $activeNames = $this->activeEnglishNames($catalog[$type] ?? []);
            $seenSlugs = [];

            foreach ($retired[$type] ?? [] as $index => $entry) {
                $label = $this->label("retired {$type}", $entry, $index);

                if (! is_array($entry)) {
                    $this->violations[] = "{$label}: record must be an array.";

                    continue;
                }

                $this->validateSlug($label, $entry['slug'] ?? null, $seenSlugs);

                if (in_array($entry['slug'] ?? null, $activeSlugs, true)) {
                    $this->violations[] = "{$label}: slug belongs to an active record.";
                }

                $names = $entry['names'] ?? null;

                if (! is_array($names) || $names === []) {
                    $this->violations[] = "{$label}: names must list at least one English name.";

                    continue;
                }

                foreach ($names as $name) {
                    if (! is_string($name) || trim($name) === '') {
                        $this->violations[] = "{$label}: names must contain non-empty strings.";

                        continue;
                    }

                    if (isset($activeNames[$this->normaliseName($name)])) {
                        $this->violations[] = "{$label}: name \"{$name}\" belongs to an active record.";
                    }
                }
            }
        }
    }

    /**
     * Names a record claims per locale. Previous names are English.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, array<int, string>>
     */
    private function namesByLocale(array $record): array
    {
        $names = [];

        foreach (is_array($record['translations'] ?? null) ? $record['translations'] : [] as $locale => $fields) {
            if (is_array($fields) && is_string($fields['name'] ?? null) && trim($fields['name']) !== '') {
                $names[$locale][] = $fields['name'];
            }
        }

        foreach (is_array($record['previous_names'] ?? null) ? $record['previous_names'] : [] as $name) {
            if (is_string($name) && trim($name) !== '') {
                $names['en'][] = $name;
            }
        }

        return array_map(
            fn (array $localeNames) => array_values(array_unique($localeNames)),
            $names,
        );
    }

    /**
     * @param  array<int, mixed>  $records
     * @return array<string, true>
     */
    private function activeEnglishNames(array $records): array
    {
        $names = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            foreach ($this->namesByLocale($record)['en'] ?? [] as $name) {
                $names[$this->normaliseName($name)] = true;
            }
        }

        return $names;
    }

    /**
     * @param  array<int, mixed>  $records
     * @return array<int, string>
     */
    private function slugs(array $records): array
    {
        $slugs = [];

        foreach ($records as $record) {
            if (is_array($record) && is_string($record['slug'] ?? null)) {
                $slugs[] = $record['slug'];
            }
        }

        return $slugs;
    }

    private function label(string $type, mixed $record, int|string $index): string
    {
        $slug = is_array($record) ? ($record['slug'] ?? null) : null;

        return is_string($slug) && $slug !== '' ? "{$type} [{$slug}]" : "{$type} #{$index}";
    }

    private function normaliseName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function printable(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : gettype($value);
    }
}

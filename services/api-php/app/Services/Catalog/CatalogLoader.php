<?php

namespace App\Services\Catalog;

use App\Enums\DifficultyUnit;
use App\Enums\EffortType;
use App\Enums\Weekday;
use App\Exceptions\InvalidCatalogException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the shared YAML catalog (the repository's catalog/ directory, also
 * read by the Go API) and normalises it for CatalogValidator. Values are left
 * as YAML gives them, apart from enum strings that name a case, so the
 * validator reports a wrong value with its rule; an unknown key is an error
 * here, so a typo cannot silently drop a field.
 */
class CatalogLoader
{
    /**
     * Record types in dependency order: each type only references earlier ones.
     */
    public const TYPES = ['equipment', 'categories', 'exercises', 'workout_templates', 'programs'];

    /**
     * The keys each record may have.
     */
    private const RECORD_KEYS = [
        'equipment' => ['slug', 'difficulty_unit', 'translations', 'previous_names'],
        'categories' => ['slug', 'translations', 'previous_names'],
        'exercises' => ['slug', 'equipment', 'effort_type', 'rest_time_seconds', 'categories', 'translations', 'previous_names'],
        'workout_templates' => ['slug', 'translations', 'activities', 'previous_names'],
        'programs' => ['slug', 'translations', 'schedule', 'previous_names'],
    ];

    private const ACTIVITY_KEYS = ['exercise', 'sets', 'effort', 'difficulty'];

    private const SET_KEYS = ['effort', 'difficulty'];

    private const SCHEDULE_KEYS = ['weekday', 'template'];

    private const RETIRED_KEYS = ['slug', 'names'];

    public function __construct(private readonly string $path) {}

    /**
     * Read the catalog data files and return them in normalised form.
     *
     * @return array{
     *     equipment: array<int, array<string, mixed>>,
     *     categories: array<int, array<string, mixed>>,
     *     exercises: array<int, array<string, mixed>>,
     *     workout_templates: array<int, array<string, mixed>>,
     *     programs: array<int, array<string, mixed>>,
     *     retired: array<string, array<int, array<string, mixed>>>
     * }
     *
     * @throws InvalidCatalogException
     */
    public function load(): array
    {
        if (! is_dir($this->path)) {
            throw new InvalidCatalogException(["Catalog directory [{$this->path}] does not exist."]);
        }

        return [
            'equipment' => $this->readList('equipment.yaml', 'equipment'),
            'categories' => $this->readList('categories.yaml', 'categories'),
            'exercises' => $this->readDirectory('exercises', 'exercises'),
            'workout_templates' => $this->readDirectory('workout-templates', 'workout_templates'),
            'programs' => $this->readList('programs.yaml', 'programs'),
            'retired' => $this->readRetired(),
        ];
    }

    /**
     * Decode one file; null when it does not exist.
     *
     * @throws InvalidCatalogException
     */
    private function read(string $relativePath): mixed
    {
        $file = $this->path.DIRECTORY_SEPARATOR.$relativePath;

        if (! is_file($file)) {
            return null;
        }

        try {
            return Yaml::parseFile($file) ?? [];
        } catch (ParseException $e) {
            throw new InvalidCatalogException(["Catalog file [{$relativePath}]: {$e->getMessage()}"]);
        }
    }

    /**
     * Read a required file holding a list of records.
     *
     * @return array<int, mixed>
     *
     * @throws InvalidCatalogException
     */
    private function readList(string $relativePath, string $type): array
    {
        $records = $this->read($relativePath);

        if ($records === null) {
            throw new InvalidCatalogException(["Catalog file [{$relativePath}] does not exist."]);
        }

        if (! is_array($records) || ! array_is_list($records)) {
            throw new InvalidCatalogException(["Catalog file [{$relativePath}] must hold a list of records."]);
        }

        return array_map(
            fn (mixed $record) => is_array($record) ? $this->normaliseRecord($relativePath, $type, $record) : $record,
            $records,
        );
    }

    /**
     * Merge every file of a directory, in file name order.
     *
     * @return array<int, mixed>
     *
     * @throws InvalidCatalogException
     */
    private function readDirectory(string $directory, string $type): array
    {
        $files = glob($this->path.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'*.yaml') ?: [];

        if ($files === []) {
            throw new InvalidCatalogException(["Catalog directory [{$directory}] has no data files."]);
        }

        sort($files);

        $records = [];

        foreach ($files as $file) {
            array_push($records, ...$this->readList($directory.DIRECTORY_SEPARATOR.basename($file), $type));
        }

        return $records;
    }

    /**
     * The optional retired.yaml, by type. Every record type is present; a type
     * the catalog does not have is kept for the validator to report.
     *
     * @return array<string, array<int, mixed>>
     *
     * @throws InvalidCatalogException
     */
    private function readRetired(): array
    {
        $retired = $this->read('retired.yaml') ?? [];

        if (! is_array($retired) || ($retired !== [] && array_is_list($retired))) {
            throw new InvalidCatalogException(['Catalog file [retired.yaml] must map record types to lists.']);
        }

        foreach ($retired as $type => $entries) {
            $entries ??= [];

            if (! is_array($entries) || ! array_is_list($entries)) {
                throw new InvalidCatalogException(["Catalog file [retired.yaml]: {$type} must be a list."]);
            }

            foreach ($entries as $entry) {
                $this->checkKeys("retired.yaml: {$type}", $entry, self::RETIRED_KEYS);
            }

            $retired[$type] = $entries;
        }

        foreach (self::TYPES as $type) {
            $retired[$type] ??= [];
        }

        return $retired;
    }

    /**
     * Fill the defaults, map enum strings to their cases and expand the set
     * shorthand.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     *
     * @throws InvalidCatalogException
     */
    private function normaliseRecord(string $file, string $type, array $record): array
    {
        $context = "{$file}: {$type} ".$this->label($record);

        $this->checkKeys($context, $record, self::RECORD_KEYS[$type]);

        $record['previous_names'] ??= [];

        if ($type === 'equipment') {
            $record['difficulty_unit'] = $this->enum(DifficultyUnit::class, $record['difficulty_unit'] ?? null);
        }

        if ($type === 'exercises') {
            $record['effort_type'] = $this->enum(EffortType::class, $record['effort_type'] ?? null);
            $record['rest_time_seconds'] ??= null;
        }

        if ($type === 'workout_templates' && is_array($record['activities'] ?? null)) {
            $record['activities'] = array_map(
                fn (mixed $activity, int $position) => is_array($activity)
                    ? $this->normaliseActivity($context.' activity '.($position + 1), $activity)
                    : $activity,
                array_values($record['activities']),
                array_keys(array_values($record['activities'])),
            );
        }

        if ($type === 'programs' && is_array($record['schedule'] ?? null)) {
            $record['schedule'] = array_map(
                fn (mixed $entry) => is_array($entry) ? $this->normaliseScheduleEntry($context, $entry) : $entry,
                array_values($record['schedule']),
            );
        }

        return $record;
    }

    /**
     * Expand the `sets: 3, effort: 10` shorthand into an explicit list of sets.
     *
     * @param  array<string, mixed>  $activity
     * @return array<string, mixed>
     *
     * @throws InvalidCatalogException
     */
    private function normaliseActivity(string $context, array $activity): array
    {
        $this->checkKeys($context, $activity, self::ACTIVITY_KEYS);

        $sets = $activity['sets'] ?? null;

        if (is_int($sets)) {
            $set = [
                'effort' => $activity['effort'] ?? null,
                'difficulty' => $activity['difficulty'] ?? null,
            ];

            $sets = $sets > 0 ? array_fill(0, $sets, $set) : [];
        } elseif (is_array($sets)) {
            $sets = array_values($sets);

            foreach ($sets as $position => $set) {
                if (is_array($set)) {
                    $this->checkKeys("{$context} set ".($position + 1), $set, self::SET_KEYS);
                    $sets[$position] = ['effort' => $set['effort'] ?? null, 'difficulty' => $set['difficulty'] ?? null];
                }
            }
        }

        return [
            'exercise' => $activity['exercise'] ?? null,
            'sets' => $sets,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     *
     * @throws InvalidCatalogException
     */
    private function normaliseScheduleEntry(string $context, array $entry): array
    {
        $this->checkKeys("{$context} schedule", $entry, self::SCHEDULE_KEYS);

        if (array_key_exists('weekday', $entry)) {
            $entry['weekday'] = $this->enum(Weekday::class, $entry['weekday']);
        }

        return $entry;
    }

    /**
     * The case a string names, or the value unchanged so the validator reports it.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|mixed
     */
    private function enum(string $enum, mixed $value): mixed
    {
        return is_string($value) ? ($enum::tryFrom($value) ?? $value) : $value;
    }

    /**
     * @param  array<int, string>  $allowed
     *
     * @throws InvalidCatalogException
     */
    private function checkKeys(string $context, mixed $value, array $allowed): void
    {
        if (! is_array($value)) {
            return;
        }

        $unknown = array_diff(array_map('strval', array_keys($value)), $allowed);
        sort($unknown);

        if ($unknown !== []) {
            throw new InvalidCatalogException(["Catalog file [{$context}]: unknown key \"{$unknown[0]}\"."]);
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function label(array $record): string
    {
        $slug = $record['slug'] ?? null;

        return is_string($slug) && $slug !== '' ? "[{$slug}]" : '(no slug)';
    }
}

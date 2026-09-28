<?php

namespace App\Services\Catalog;

use App\Exceptions\InvalidCatalogException;

class CatalogLoader
{
    /**
     * Record types in dependency order: each type only references earlier ones.
     */
    public const TYPES = ['equipment', 'categories', 'exercises', 'workout_templates', 'programs'];

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

        $catalog = [
            'equipment' => $this->readFile('equipment.php'),
            'categories' => $this->readFile('categories.php'),
            'exercises' => $this->readDirectory('exercises'),
            'workout_templates' => $this->readDirectory('workout-templates'),
            'programs' => $this->readFile('programs.php'),
        ];

        foreach (self::TYPES as $type) {
            $catalog[$type] = array_map(
                fn (mixed $record) => is_array($record) ? $this->normaliseRecord($type, $record) : $record,
                array_values($catalog[$type]),
            );
        }

        $retired = $this->readFile('retired.php', required: false);

        foreach (self::TYPES as $type) {
            $catalog['retired'][$type] = array_values($retired[$type] ?? []);
        }

        return $catalog;
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws InvalidCatalogException
     */
    private function readFile(string $relativePath, bool $required = true): array
    {
        $file = $this->path.DIRECTORY_SEPARATOR.$relativePath;

        if (! is_file($file)) {
            if ($required) {
                throw new InvalidCatalogException(["Catalog file [{$relativePath}] does not exist."]);
            }

            return [];
        }

        $data = require $file;

        if (! is_array($data)) {
            throw new InvalidCatalogException(["Catalog file [{$relativePath}] must return an array."]);
        }

        return $data;
    }

    /**
     * Merge every file of a directory, in file name order.
     *
     * @return array<int, mixed>
     *
     * @throws InvalidCatalogException
     */
    private function readDirectory(string $directory): array
    {
        $files = glob($this->path.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'*.php') ?: [];

        if ($files === []) {
            throw new InvalidCatalogException(["Catalog directory [{$directory}] has no data files."]);
        }

        sort($files);

        $records = [];

        foreach ($files as $file) {
            $relativePath = $directory.DIRECTORY_SEPARATOR.basename($file);

            array_push($records, ...array_values($this->readFile($relativePath)));
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normaliseRecord(string $type, array $record): array
    {
        $record['previous_names'] ??= [];

        if ($type === 'exercises') {
            $record['rest_time_seconds'] ??= null;
        }

        if ($type === 'workout_templates' && is_array($record['activities'] ?? null)) {
            $record['activities'] = array_map(
                fn (mixed $activity) => is_array($activity) ? $this->normaliseActivity($activity) : $activity,
                array_values($record['activities']),
            );
        }

        return $record;
    }

    /**
     * Expand the `'sets' => 3, 'effort' => 10` shorthand into an explicit list of sets.
     *
     * @param  array<string, mixed>  $activity
     * @return array<string, mixed>
     */
    private function normaliseActivity(array $activity): array
    {
        $sets = $activity['sets'] ?? null;

        if (is_int($sets)) {
            $set = [
                'effort' => $activity['effort'] ?? null,
                'difficulty' => $activity['difficulty'] ?? null,
            ];

            $sets = $sets > 0 ? array_fill(0, $sets, $set) : [];
        } elseif (is_array($sets)) {
            $sets = array_map(
                fn (mixed $set) => is_array($set)
                    ? ['effort' => $set['effort'] ?? null, 'difficulty' => $set['difficulty'] ?? null]
                    : $set,
                array_values($sets),
            );
        }

        return [
            'exercise' => $activity['exercise'] ?? null,
            'sets' => $sets,
        ];
    }
}

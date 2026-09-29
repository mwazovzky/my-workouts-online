<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copies the English rows of the translations table into the columns added by
 * the previous migration, then removes those rows. Nothing is written when a
 * record has no English name, so the run can be repeated after fixing the data.
 */
return new class extends Migration
{
    private const SOURCE_LOCALE = 'en';

    /**
     * Table => [morph alias, translatable fields].
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    private array $tables = [
        'equipment' => ['equipment', ['name']],
        'categories' => ['category', ['name']],
        'exercises' => ['exercise', ['name', 'description']],
        'workout_templates' => ['workout_template', ['name', 'description']],
        'programs' => ['program', ['name', 'description']],
    ];

    public function up(): void
    {
        $this->assertEveryRecordHasASourceName();

        DB::transaction(function () {
            foreach ($this->tables as $tableName => [$alias, $fields]) {
                $this->sourceRows($alias)
                    ->whereIn('field', $fields)
                    ->lazyById()
                    ->each(fn (object $row) => DB::table($tableName)
                        ->where('id', $row->translatable_id)
                        ->update([$row->field => $row->value]));

                $this->sourceRows($alias)->delete();
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach ($this->tables as $tableName => [$alias, $fields]) {
                foreach ($fields as $field) {
                    DB::table($tableName)
                        ->whereNotNull($field)
                        ->lazyById()
                        ->each(fn (object $row) => DB::table('translations')->insertOrIgnore([
                            'translatable_type' => $alias,
                            'translatable_id' => $row->id,
                            'locale' => self::SOURCE_LOCALE,
                            'field' => $field,
                            'value' => $row->{$field},
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]));
                }
            }
        });
    }

    private function assertEveryRecordHasASourceName(): void
    {
        $missing = [];

        foreach ($this->tables as $tableName => [$alias]) {
            $ids = DB::table($tableName)
                ->whereNull('name')
                ->whereNotIn('id', $this->sourceRows($alias)->where('field', 'name')->select('translatable_id'))
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                $missing[] = $tableName.' (ids '.implode(', ', $ids).')';
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Records without an English name: '.implode('; ', $missing).'. Give each one a name, then run the migration again.');
        }
    }

    private function sourceRows(string $alias): \Illuminate\Database\Query\Builder
    {
        return DB::table('translations')
            ->where('translatable_type', $alias)
            ->where('locale', self::SOURCE_LOCALE);
    }
};

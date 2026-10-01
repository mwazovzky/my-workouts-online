<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Translatable records keep their source text in their own columns; the
 * translations table holds the other locales only.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $tables = [
        'equipment' => ['name'],
        'categories' => ['name'],
        'exercises' => ['name', 'description'],
        'workout_templates' => ['name', 'description'],
        'programs' => ['name', 'description'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $fields) {
            Schema::table($tableName, function (Blueprint $table) use ($fields) {
                $table->string('name')->nullable()->after('slug');

                if (in_array('description', $fields, true)) {
                    $table->text('description')->nullable()->after('name');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName => $fields) {
            Schema::table($tableName, function (Blueprint $table) use ($fields) {
                $table->dropColumn($fields);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A slug is the stable key of a record managed by the catalog data files in
 * database/catalog. Records created through the admin UI have no slug.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'equipment',
        'categories',
        'exercises',
        'workout_templates',
        'programs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('slug', 100)->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique($tableName.'_slug_unique');
                $table->dropColumn('slug');
            });
        }
    }
};

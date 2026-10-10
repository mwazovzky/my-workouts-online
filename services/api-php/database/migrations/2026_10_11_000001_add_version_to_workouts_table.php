<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A counter bumped on every change to a workout or its activities; the `ETag` is built from it.
     */
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};

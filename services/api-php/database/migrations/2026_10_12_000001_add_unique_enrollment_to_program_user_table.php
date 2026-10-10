<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes duplicate enrollments, keeping the earliest row of each pair, then makes
     * (user_id, program_id) unique so a concurrent enroll can never insert a second row.
     */
    public function up(): void
    {
        $duplicates = DB::table('program_user')
            ->select('user_id', 'program_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'program_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('program_user')
                ->where('user_id', $duplicate->user_id)
                ->where('program_id', $duplicate->program_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('program_user', function (Blueprint $table) {
            $table->unique(['user_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::table('program_user', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'program_id']);
        });
    }
};

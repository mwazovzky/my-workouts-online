<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * Rewrites the stored weekdays to the lowercase `Weekday` backing values.
     */
    public function up(): void
    {
        $this->rewrite(fn (string $day) => strtolower($day), array_map('strtolower', $this->days));
    }

    public function down(): void
    {
        $this->rewrite(fn (string $day) => ucfirst($day), $this->days);
    }

    /**
     * Widens the column to a string, rewrites every value, then narrows it to the target enum.
     *
     * @param  callable(string): string  $convert
     * @param  array<int, string>  $values
     */
    private function rewrite(callable $convert, array $values): void
    {
        Schema::table('program_workout_template', function (Blueprint $table) {
            $table->string('weekday', 16)->change();
        });

        foreach ($this->days as $day) {
            DB::table('program_workout_template')
                ->whereIn('weekday', [$day, strtolower($day)])
                ->update(['weekday' => $convert($day)]);
        }

        Schema::table('program_workout_template', function (Blueprint $table) use ($values) {
            $table->enum('weekday', $values)->change();
        });
    }
};

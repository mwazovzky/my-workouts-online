<?php

namespace Tests\Feature\Migrations;

use App\Models\Program;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LowercaseWeekdayMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = '2026_10_10_000001_lowercase_program_workout_template_weekday';

    #[Test]
    public function it_lowercases_stored_weekdays(): void
    {
        $this->migration()->down();
        $ids = $this->assign(['Monday', 'Sunday']);

        $this->migration()->up();

        $this->assertSame(['monday', 'sunday'], $this->weekdays($ids));
    }

    #[Test]
    public function it_restores_capitalised_weekdays_on_rollback(): void
    {
        $ids = $this->assign(['wednesday', 'friday']);

        $this->migration()->down();

        $this->assertSame(['Wednesday', 'Friday'], $this->weekdays($ids));
    }

    /**
     * @param  array<int, string>  $weekdays
     * @return array<int, int>
     */
    private function assign(array $weekdays): array
    {
        $program = Program::factory()->create();
        $template = WorkoutTemplate::factory()->create();

        return array_map(fn (string $weekday) => DB::table('program_workout_template')->insertGetId([
            'program_id' => $program->id,
            'workout_template_id' => $template->id,
            'weekday' => $weekday,
        ]), $weekdays);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function weekdays(array $ids): array
    {
        return array_map(
            fn (int $id) => DB::table('program_workout_template')->where('id', $id)->value('weekday'),
            $ids,
        );
    }

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION.'.php');
    }
}

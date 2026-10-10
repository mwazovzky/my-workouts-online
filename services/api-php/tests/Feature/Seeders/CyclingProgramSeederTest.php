<?php

namespace Tests\Feature\Seeders;

use App\Enums\DifficultyUnit;
use App\Enums\EffortType;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\WorkoutTemplate;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The cycling program is seeded as part of the catalog.
 */
class CyclingProgramSeederTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(): Program
    {
        $this->seed(CatalogSeeder::class);

        return Program::whereTranslated('name', 'Cycling — Beginner (Week 1)', 'en')->firstOrFail();
    }

    private function assignmentCount(Program $program): int
    {
        return DB::table('program_workout_template')->where('program_id', $program->id)->count();
    }

    #[Test]
    public function it_seeds_the_program_with_six_weekday_assignments(): void
    {
        $program = $this->runSeeder();

        $this->assertSame(6, $program->workoutTemplates()->count());
        $this->assertSame(6, $this->assignmentCount($program));
    }

    #[Test]
    public function it_seeds_an_indoor_bike_measured_in_heart_rate_zones(): void
    {
        $this->runSeeder();

        $bike = Equipment::whereTranslated('name', 'Indoor Bike', 'en')->firstOrFail();

        $this->assertSame(DifficultyUnit::HeartRateZone, $bike->difficulty_unit);
    }

    #[Test]
    public function it_seeds_duration_based_cycling_exercises(): void
    {
        $this->runSeeder();

        $endurance = Exercise::whereTranslated('name', 'Endurance Ride', 'en')->firstOrFail();
        $bike = Equipment::whereTranslated('name', 'Indoor Bike', 'en')->firstOrFail();

        $this->assertSame(EffortType::Duration, $endurance->effort_type);
        $this->assertSame(6, $bike->exercises()->count());
        $this->assertSame(6, $bike->exercises()->where('effort_type', EffortType::Duration)->count());
    }

    #[Test]
    public function it_schedules_the_same_template_on_tuesday_and_thursday(): void
    {
        $program = $this->runSeeder();

        $tuesday = $program->workoutTemplates()->wherePivot('weekday', 'tuesday')->firstOrFail();
        $thursday = $program->workoutTemplates()->wherePivot('weekday', 'thursday')->firstOrFail();

        $this->assertSame($tuesday->id, $thursday->id);
    }

    #[Test]
    public function the_recovery_ride_targets_zone_one_for_thirty_minutes(): void
    {
        $program = $this->runSeeder();

        $monday = $program->workoutTemplates()
            ->wherePivot('weekday', 'monday')
            ->with(['activities.sets'])
            ->firstOrFail();

        $set = $monday->activities->first()->sets->first();

        $this->assertSame(1800, $set->effort_value);
        $this->assertSame(1.0, $set->difficulty_value);
    }

    #[Test]
    public function a_drill_set_can_have_no_target_zone(): void
    {
        $this->runSeeder();

        $template = WorkoutTemplate::whereTranslated('name', 'Endurance + FastPedal (short)', 'en')
            ->with(['activities' => fn ($q) => $q->orderBy('order'), 'activities.sets'])
            ->firstOrFail();

        $fastPedalSet = $template->activities->last()->sets->first();

        $this->assertNull($fastPedalSet->difficulty_value);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->seed(CatalogSeeder::class);

        $program = Program::whereTranslated('name', 'Cycling — Beginner (Week 1)', 'en')->firstOrFail();

        $this->assertSame(1, Program::whereTranslated('name', 'Cycling — Beginner (Week 1)', 'en')->count());
        $this->assertSame(6, $this->assignmentCount($program));
    }
}

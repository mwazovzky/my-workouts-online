<?php

namespace Tests\Unit\Rules;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Rules\HeartRateZoneWithinRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HeartRateZoneWithinRangeTest extends TestCase
{
    use RefreshDatabase;

    private function zoneExercise(): Exercise
    {
        $equipment = Equipment::factory()->heartRateZone()->create();

        return Exercise::factory()->create(['equipment_id' => $equipment->id]);
    }

    #[Test]
    public function it_resolves_the_exercise_unit_once_per_validation_pass(): void
    {
        $exercise = $this->zoneExercise();
        request()->merge(['activities' => [['exercise_id' => $exercise->id]]]);

        $rule = new HeartRateZoneWithinRange;
        $failed = false;
        $fail = function () use (&$failed) {
            $failed = true;
        };

        // Warm the memo with the first set.
        $rule->validate('activities.0.sets.0.difficulty_value', 2, $fail);

        // Subsequent sets for the same exercise must not hit the database.
        DB::enableQueryLog();
        DB::flushQueryLog();
        for ($i = 1; $i < 5; $i++) {
            $rule->validate("activities.0.sets.{$i}.difficulty_value", 2, $fail);
        }

        $this->assertFalse($failed);
        $this->assertCount(0, DB::getQueryLog());
    }

    #[Test]
    public function it_passes_a_difficulty_value_on_a_non_zone_exercise(): void
    {
        $exercise = Exercise::factory()->create(); // default equipment = kilograms
        request()->merge(['activities' => [['exercise_id' => $exercise->id]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            120,
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertFalse($failed);
    }

    #[Test]
    public function it_rejects_an_out_of_range_zone(): void
    {
        $exercise = $this->zoneExercise();
        request()->merge(['activities' => [['exercise_id' => $exercise->id]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            7,
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertTrue($failed);
    }

    #[Test]
    public function it_ignores_a_null_difficulty_value(): void
    {
        $exercise = $this->zoneExercise();
        request()->merge(['activities' => [['exercise_id' => $exercise->id]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            null,
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertFalse($failed);
    }

    #[Test]
    public function it_ignores_a_set_whose_activity_has_no_exercise_id(): void
    {
        request()->merge(['activities' => [['exercise_id' => null]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            9,
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertFalse($failed);
    }

    #[Test]
    public function it_rejects_a_zone_below_the_valid_range(): void
    {
        $exercise = $this->zoneExercise();
        request()->merge(['activities' => [['exercise_id' => $exercise->id]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            0,
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertTrue($failed);
    }

    #[Test]
    public function it_rejects_a_non_integer_zone(): void
    {
        $exercise = $this->zoneExercise();
        request()->merge(['activities' => [['exercise_id' => $exercise->id]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            2.5,
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertTrue($failed);
    }

    #[Test]
    public function it_rejects_a_non_numeric_zone(): void
    {
        $exercise = $this->zoneExercise();
        request()->merge(['activities' => [['exercise_id' => $exercise->id]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            'hard',
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertTrue($failed);
    }

    #[Test]
    public function it_ignores_a_zone_value_for_an_exercise_that_no_longer_exists(): void
    {
        request()->merge(['activities' => [['exercise_id' => 999999]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            9,
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertFalse($failed);
    }

    #[Test]
    public function it_still_validates_a_retired_exercise(): void
    {
        $exercise = $this->zoneExercise();
        $exercise->delete(); // soft delete
        request()->merge(['activities' => [['exercise_id' => $exercise->id]]]);

        $failed = false;
        (new HeartRateZoneWithinRange)->validate(
            'activities.0.sets.0.difficulty_value',
            9,
            function () use (&$failed) {
                $failed = true;
            },
        );

        $this->assertTrue($failed);
    }
}

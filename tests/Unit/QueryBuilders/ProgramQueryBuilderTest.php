<?php

namespace Tests\Unit\QueryBuilders;

use App\Models\Program;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\QueryBuilders\ProgramQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_user_returns_programs_with_templates(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Alpha Program']);
        $program->users()->attach($user->id);
        $workoutTemplates = WorkoutTemplate::factory()->count(3)->create();

        foreach ($workoutTemplates as $index => $workoutTemplate) {
            $program->workoutTemplates()->attach($workoutTemplate, ['weekday' => ['Monday', 'Wednesday', 'Friday'][$index]]);
        }

        $builder = new ProgramQueryBuilder;
        $result = $builder->for($user)->get();

        $this->assertNotEmpty($result);
        $this->assertEquals(1, $result->count());
        $this->assertTrue($result->first()->relationLoaded('workoutTemplates'));
        $this->assertEquals($program->id, $result->first()->id);
    }

    public function test_for_other_user_returns_no_programs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Alpha Program']);
        $program->users()->attach($user->id);
        $workoutTemplates = WorkoutTemplate::factory()->count(3)->create();

        foreach ($workoutTemplates as $index => $workoutTemplate) {
            $program->workoutTemplates()->attach($workoutTemplate, ['weekday' => ['Monday', 'Wednesday', 'Friday'][$index]]);
        }

        $builder = new ProgramQueryBuilder;
        $result = $builder->for($otherUser)->get();

        $this->assertTrue($result->isEmpty());
    }
}

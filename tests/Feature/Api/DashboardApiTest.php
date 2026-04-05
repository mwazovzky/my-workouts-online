<?php

namespace Tests\Feature\Api;

use App\Enums\WorkoutStatus;
use App\Models\Program;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-01-06 10:00:00'); // Monday
    }

    #[Test]
    public function index_returns_dashboard_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'upcoming_schedule',
                'in_progress_workout',
                'recent_workouts',
                'summary' => [
                    'enrolled_programs_count',
                    'completed_workouts_count',
                    'completed_last_7_days_count',
                    'upcoming_workouts_count',
                ],
            ],
        ]);
    }

    #[Test]
    public function index_includes_in_progress_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => WorkoutStatus::InProgress]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.in_progress_workout.id', $workout->id);
    }

    #[Test]
    public function index_in_progress_workout_is_null_when_none_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.in_progress_workout', null);
    }

    #[Test]
    public function index_returns_correct_summary_counts(): void
    {
        $user = User::factory()->create();

        $program = Program::factory()->create();
        $user->programs()->attach($program->id);

        Workout::factory()->create(['user_id' => $user->id, 'status' => WorkoutStatus::Completed, 'updated_at' => now()->subDays(1)]);
        Workout::factory()->create(['user_id' => $user->id, 'status' => WorkoutStatus::Completed, 'updated_at' => now()->subDays(10)]);
        Workout::factory()->create(['user_id' => $user->id, 'status' => WorkoutStatus::InProgress]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.summary.enrolled_programs_count', 1);
        $response->assertJsonPath('data.summary.completed_workouts_count', 2);
        $response->assertJsonPath('data.summary.completed_last_7_days_count', 1);
    }

    #[Test]
    public function index_includes_upcoming_schedule_for_enrolled_programs(): void
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create();
        $program = Program::factory()->create();
        $program->workoutTemplates()->attach($template->id, ['weekday' => 'Tuesday']);
        $user->programs()->attach($program->id);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.upcoming_schedule'));
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }
}

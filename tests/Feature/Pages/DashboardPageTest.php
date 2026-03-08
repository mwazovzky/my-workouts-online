<?php

namespace Tests\Feature\Pages;

use App\Enums\WorkoutStatus;
use App\Models\Program;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function dashboard_page_is_rendered_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
        );
    }

    #[Test]
    public function dashboard_page_shows_upcoming_schedule_summary_and_recent_workouts(): void
    {
        CarbonImmutable::setTestNow('2026-03-08 09:00:00');

        try {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $lastUpdatedAt = CarbonImmutable::parse('2026-03-08 08:45:00');

            $program = Program::factory()->create();
            $user->programs()->attach($program);

            $mondayWorkout = WorkoutTemplate::factory()->create();
            $fridayWorkout = WorkoutTemplate::factory()->create();

            $program->workoutTemplates()->attach($mondayWorkout, ['weekday' => 'Monday']);
            $program->workoutTemplates()->attach($fridayWorkout, ['weekday' => 'Friday']);

            $inProgressWorkout = Workout::factory()->create([
                'user_id' => $user->id,
                'workout_template_id' => $mondayWorkout->id,
                'status' => WorkoutStatus::InProgress,
                'created_at' => CarbonImmutable::parse('2026-03-08 08:00:00'),
                'updated_at' => $lastUpdatedAt,
            ]);

            Workout::factory()->create([
                'user_id' => $user->id,
                'workout_template_id' => $fridayWorkout->id,
                'status' => WorkoutStatus::Completed,
            ]);

            Workout::factory()->create([
                'user_id' => $otherUser->id,
                'status' => WorkoutStatus::Completed,
            ]);

            $response = $this
                ->actingAs($user)
                ->get('/dashboard');

            $response->assertOk();

            $response->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('summary.enrolled_programs_count', 1)
                ->where('summary.completed_workouts_count', 1)
                ->where('summary.completed_last_7_days_count', 1)
                ->where('summary.upcoming_workouts_count', 2)
                ->has('upcomingSchedule', 2)
                ->where('upcomingSchedule.0.weekday', 'Monday')
                ->where('upcomingSchedule.0.scheduled_for', '2026-03-09')
                ->where('upcomingSchedule.1.weekday', 'Friday')
                ->where('upcomingSchedule.1.scheduled_for', '2026-03-13')
                ->where('inProgressWorkout.id', $inProgressWorkout->id)
                ->where('inProgressWorkout.status', WorkoutStatus::InProgress->value)
                ->where('inProgressWorkout.updated_at', $lastUpdatedAt->toJSON())
                ->has('recentWorkouts', 2)
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}

<?php

namespace Tests\Feature\Api;

use App\Enums\WorkoutStatus;
use App\Models\Activity;
use App\Models\Exercise;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // GET /api/v1/workouts
    // -------------------------------------------------------

    #[Test]
    public function index_returns_paginated_workouts_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Workout::factory()->count(2)->create(['user_id' => $user->id]);
        Workout::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/workouts');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/v1/workouts')->assertUnauthorized();
    }

    // -------------------------------------------------------
    // GET /api/v1/workouts/{id}
    // -------------------------------------------------------

    #[Test]
    public function show_returns_workout_with_activities(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workout, 'workout')->create();
        Set::factory()->for($activity, 'activity')->create(['order' => 1]);

        $response = $this->actingAs($user)->getJson("/api/v1/workouts/{$workout->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $workout->id);
        $response->assertJsonStructure(['data' => ['id', 'name', 'status', 'activities']]);
    }

    #[Test]
    public function show_returns_404_for_another_users_workout(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->getJson("/api/v1/workouts/{$workout->id}")->assertNotFound();
    }

    #[Test]
    public function show_requires_authentication(): void
    {
        $workout = Workout::factory()->create();
        $this->getJson("/api/v1/workouts/{$workout->id}")->assertUnauthorized();
    }

    // -------------------------------------------------------
    // POST /api/v1/workouts
    // -------------------------------------------------------

    #[Test]
    public function store_creates_workout_from_template(): void
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create();
        $activity = Activity::factory()->for($template, 'workout')->create();
        Set::factory()->for($activity, 'activity')->create(['order' => 1, 'effort_value' => 10, 'difficulty_value' => 50]);

        $response = $this->actingAs($user)->postJson('/api/v1/workouts', [
            'workout_template_id' => $template->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', WorkoutStatus::InProgress->value);
        $this->assertDatabaseHas('workouts', ['user_id' => $user->id, 'workout_template_id' => $template->id]);
    }

    #[Test]
    public function store_rejects_missing_template_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/workouts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workout_template_id']);
    }

    #[Test]
    public function store_rejects_nonexistent_template(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/workouts', ['workout_template_id' => 99999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workout_template_id']);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        $this->postJson('/api/v1/workouts', ['workout_template_id' => 1])->assertUnauthorized();
    }

    // -------------------------------------------------------
    // PATCH /api/v1/workouts/{workout}/save
    // -------------------------------------------------------

    #[Test]
    public function save_updates_activities_and_sets(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();
        $activity = Activity::factory()->for($workout, 'workout')->create(['exercise_id' => $exercise->id, 'order' => 1]);
        $set = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'effort_value' => 5, 'difficulty_value' => 10]);

        $response = $this->actingAs($user)->patchJson("/api/v1/workouts/{$workout->id}/save", [
            'activities' => [[
                'id' => $activity->id,
                'exercise_id' => $exercise->id,
                'order' => 1,
                'sets' => [['id' => $set->id, 'order' => 1, 'effort_value' => 20, 'difficulty_value' => 30]],
            ]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('sets', ['id' => $set->id, 'effort_value' => 20, 'difficulty_value' => 30]);
    }

    #[Test]
    public function save_returns_403_for_another_users_workout(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $other->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $this->actingAs($user)->patchJson("/api/v1/workouts/{$workout->id}/save", [
            'activities' => [['exercise_id' => $exercise->id, 'order' => 1, 'sets' => [['order' => 1, 'effort_value' => 5, 'difficulty_value' => 10]]]],
        ])->assertForbidden();
    }

    #[Test]
    public function save_returns_403_for_completed_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        $exercise = Exercise::factory()->create();

        $this->actingAs($user)->patchJson("/api/v1/workouts/{$workout->id}/save", [
            'activities' => [['exercise_id' => $exercise->id, 'order' => 1, 'sets' => [['order' => 1, 'effort_value' => 5, 'difficulty_value' => 10]]]],
        ])->assertForbidden();
    }

    // -------------------------------------------------------
    // POST /api/v1/workouts/{workout}/complete
    // -------------------------------------------------------

    #[Test]
    public function complete_marks_workout_as_completed(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => WorkoutStatus::InProgress]);

        $response = $this->actingAs($user)->postJson("/api/v1/workouts/{$workout->id}/complete");

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkoutStatus::Completed->value);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'status' => WorkoutStatus::Completed]);
    }

    #[Test]
    public function complete_returns_403_for_another_users_workout(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $other->id, 'status' => WorkoutStatus::InProgress]);

        $this->actingAs($user)->postJson("/api/v1/workouts/{$workout->id}/complete")->assertForbidden();
    }

    #[Test]
    public function complete_returns_403_for_already_completed_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => WorkoutStatus::Completed]);

        $this->actingAs($user)->postJson("/api/v1/workouts/{$workout->id}/complete")->assertForbidden();
    }

    // -------------------------------------------------------
    // POST /api/v1/workouts/{workout}/repeat
    // -------------------------------------------------------

    #[Test]
    public function repeat_creates_new_in_progress_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => WorkoutStatus::Completed]);
        $activity = Activity::factory()->for($workout, 'workout')->create(['order' => 1]);
        Set::factory()->for($activity, 'activity')->create(['order' => 1, 'effort_value' => 10, 'difficulty_value' => 50]);

        $response = $this->actingAs($user)->postJson("/api/v1/workouts/{$workout->id}/repeat");

        $response->assertCreated();
        $response->assertJsonPath('data.status', WorkoutStatus::InProgress->value);
        $this->assertDatabaseCount('workouts', 2);
    }

    #[Test]
    public function repeat_returns_403_for_in_progress_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => WorkoutStatus::InProgress]);

        $this->actingAs($user)->postJson("/api/v1/workouts/{$workout->id}/repeat")->assertForbidden();
    }

    // -------------------------------------------------------
    // DELETE /api/v1/workouts/{workout}
    // -------------------------------------------------------

    #[Test]
    public function destroy_deletes_the_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/v1/workouts/{$workout->id}")->assertNoContent();

        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    }

    #[Test]
    public function destroy_returns_403_for_another_users_workout(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->deleteJson("/api/v1/workouts/{$workout->id}")->assertForbidden();
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $workout = Workout::factory()->create();
        $this->deleteJson("/api/v1/workouts/{$workout->id}")->assertUnauthorized();
    }
}

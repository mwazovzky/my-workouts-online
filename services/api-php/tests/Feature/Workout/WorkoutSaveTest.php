<?php

namespace Tests\Feature\Workout;

use App\Models\Activity;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutSaveTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // Happy paths
    // -------------------------------------------------------

    #[Test]
    public function save_updates_existing_sets(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $set = Set::factory()->for($activity, 'activity')->create([
            'order' => 1,
            'effort_value' => 5,
            'difficulty_value' => 50,
            'is_completed' => false,
        ]);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            [
                                'id' => $set->id,
                                'order' => 1,
                                'effort_value' => 10,
                                'difficulty_value' => 80,
                                'is_completed' => true,
                            ],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('sets', [
            'id' => $set->id,
            'effort_value' => 10,
            'difficulty_value' => 80,
            'is_completed' => true,
        ]);
    }

    #[Test]
    public function save_creates_new_sets_and_deletes_removed_ones(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2]);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['id' => $set1->id, 'order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                            ['order' => 2, 'effort_value' => 8, 'difficulty_value' => 60],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('sets', ['id' => $set1->id]);
        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);
        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 2,
            'effort_value' => 8,
            'difficulty_value' => 60,
        ]);
    }

    #[Test]
    public function save_deletes_activities_not_in_payload(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity1 = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        $activity2 = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 2,
        ]);

        Set::factory()->for($activity1, 'activity')->create(['order' => 1]);
        $set2 = Set::factory()->for($activity2, 'activity')->create(['order' => 1]);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $activity1->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('activities', ['id' => $activity1->id]);
        $this->assertDatabaseMissing('activities', ['id' => $activity2->id]);
        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);
    }

    #[Test]
    public function save_creates_new_activities(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();

        $activity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        Set::factory()->for($activity, 'activity')->create(['order' => 1]);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise1->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                    [
                        'exercise_id' => $exercise2->id,
                        'order' => 2,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 10, 'difficulty_value' => 100],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseCount('activities', 2);
        $this->assertDatabaseHas('activities', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
        ]);
    }

    #[Test]
    public function save_appending_an_activity_leaves_the_source_template_untouched(): void
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create();
        $exercise = Exercise::factory()->create();
        $addedExercise = Exercise::factory()->create();

        $templateActivity = Activity::factory()->for($template, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        Set::factory()->for($templateActivity, 'activity')->create(['order' => 1]);

        $workout = $this->actingAs($user)
            ->postJson('/api/v1/workouts', ['workout_template_id' => $template->id])
            ->assertCreated();

        $workoutId = $workout->json('data.id');
        $activityId = Activity::query()
            ->where('workout_type', 'workout')
            ->where('workout_id', $workoutId)
            ->value('id');

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workoutId.'/save',
            [
                'activities' => [
                    [
                        'id' => $activityId,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                    [
                        'exercise_id' => $addedExercise->id,
                        'order' => 2,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 12, 'difficulty_value' => 20],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('activities', [
            'workout_type' => 'workout',
            'workout_id' => $workoutId,
            'exercise_id' => $addedExercise->id,
            'order' => 2,
        ]);

        $this->assertSame(1, $template->activities()->count());
        $this->assertDatabaseHas('activities', [
            'id' => $templateActivity->id,
            'workout_type' => 'workout_template',
            'workout_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        $this->assertDatabaseMissing('activities', [
            'workout_type' => 'workout_template',
            'exercise_id' => $addedExercise->id,
        ]);
    }

    #[Test]
    public function save_response_exposes_persisted_ids_and_exercise_details_for_new_activities(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $equipment = Equipment::factory()->withTranslation('name', 'Barbell')->create();
        $exercise = Exercise::factory()
            ->withTranslation('name', 'Bench Press')
            ->for($equipment)
            ->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 10, 'difficulty_value' => 60],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $activity = $response->json('data.activities.0');
        $this->assertNotNull($activity['id']);
        $this->assertNotNull($activity['sets'][0]['id']);
        $this->assertSame('Bench Press', $activity['exercise_name']);
        $this->assertSame('Barbell', $activity['exercise_equipment_name']);
        $this->assertSame('kilograms', $activity['exercise_difficulty_unit']);
    }

    #[Test]
    public function save_twice_with_returned_ids_does_not_duplicate_the_added_activity(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        $set = Set::factory()->for($activity, 'activity')->create(['order' => 1]);

        $addedExercise = Exercise::factory()->create();

        $payload = [
            'activities' => [
                [
                    'id' => $activity->id,
                    'exercise_id' => $exercise->id,
                    'order' => 1,
                    'sets' => [
                        ['id' => $set->id, 'order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                    ],
                ],
                [
                    'exercise_id' => $addedExercise->id,
                    'order' => 2,
                    'sets' => [
                        ['order' => 1, 'effort_value' => 12, 'difficulty_value' => 20],
                    ],
                ],
            ],
        ];

        $first = $this->actingAs($user)->patchJson('/api/v1/workouts/'.$workout->id.'/save', $payload);
        $first->assertOk();

        $addedActivity = $first->json('data.activities.1');
        $payload['activities'][1]['id'] = $addedActivity['id'];
        $payload['activities'][1]['sets'][0]['id'] = $addedActivity['sets'][0]['id'];

        // Replaying the response IDs must update the same rows, not recreate them.
        $second = $this->actingAs($user)->patchJson('/api/v1/workouts/'.$workout->id.'/save', $payload);
        $second->assertOk();

        $this->assertDatabaseCount('activities', 2);
        $this->assertDatabaseCount('sets', 2);
        $this->assertDatabaseHas('activities', [
            'id' => $addedActivity['id'],
            'exercise_id' => $addedExercise->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas('sets', [
            'id' => $addedActivity['sets'][0]['id'],
            'activity_id' => $addedActivity['id'],
            'effort_value' => 12,
        ]);
    }

    #[Test]
    public function save_handles_complex_diff_multiple_deletes_and_adds(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'effort_value' => 6, 'difficulty_value' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'effort_value' => 7, 'difficulty_value' => 70]);
        $set4 = Set::factory()->for($activity, 'activity')->create(['order' => 4, 'effort_value' => 8, 'difficulty_value' => 80]);

        // Keep set1 (updated), delete set2 and set4, keep set3, add two new
        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['id' => $set1->id, 'order' => 1, 'effort_value' => 12, 'difficulty_value' => 55],
                            ['id' => $set3->id, 'order' => 2, 'effort_value' => 7, 'difficulty_value' => 70],
                            ['order' => 3, 'effort_value' => 10, 'difficulty_value' => 100],
                            ['order' => 4, 'effort_value' => 11, 'difficulty_value' => 110],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);
        $this->assertDatabaseMissing('sets', ['id' => $set4->id]);
        $this->assertDatabaseHas('sets', ['id' => $set1->id, 'order' => 1, 'effort_value' => 12, 'difficulty_value' => 55]);
        $this->assertDatabaseHas('sets', ['id' => $set3->id, 'order' => 2]);
        $this->assertEquals(4, Set::where('activity_id', $activity->id)->count());
    }

    #[Test]
    public function save_preserves_set_order_as_sent_by_frontend(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'effort_value' => 6, 'difficulty_value' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'effort_value' => 7, 'difficulty_value' => 70]);

        // Frontend sends already-normalized orders [1, 2] after deleting the middle set
        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['id' => $set1->id, 'order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                            ['id' => $set3->id, 'order' => 2, 'effort_value' => 7, 'difficulty_value' => 70],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);
        $this->assertDatabaseHas('sets', ['id' => $set1->id, 'order' => 1]);
        $this->assertDatabaseHas('sets', ['id' => $set3->id, 'order' => 2]);
    }

    #[Test]
    public function save_updates_activity_order_as_sent_by_frontend(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity1 = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        $activity2 = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 2,
        ]);

        $set1 = Set::factory()->for($activity1, 'activity')->create(['order' => 1]);
        $set2 = Set::factory()->for($activity2, 'activity')->create(['order' => 1]);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $activity1->id,
                        'exercise_id' => $exercise->id,
                        'order' => 2,
                        'sets' => [
                            [
                                'id' => $set1->id,
                                'order' => 1,
                                'effort_value' => 5,
                                'difficulty_value' => 50,
                            ],
                        ],
                    ],
                    [
                        'id' => $activity2->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            [
                                'id' => $set2->id,
                                'order' => 1,
                                'effort_value' => 6,
                                'difficulty_value' => 60,
                            ],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('activities', ['id' => $activity1->id, 'order' => 2]);
        $this->assertDatabaseHas('activities', ['id' => $activity2->id, 'order' => 1]);
    }

    // -------------------------------------------------------
    // Authorization
    // -------------------------------------------------------

    #[Test]
    public function user_cannot_save_another_users_workout(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $otherUser->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertForbidden();
    }

    #[Test]
    public function user_cannot_save_completed_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertForbidden();
    }

    #[Test]
    public function guest_cannot_save_workout(): void
    {
        $workout = Workout::factory()->create(['status' => 'in_progress']);

        $response = $this->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            ['activities' => []],
        );

        $response->assertUnauthorized();
    }

    #[Test]
    public function save_nonexistent_workout_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/99999/save',
            ['activities' => []],
        );

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // Validation
    // -------------------------------------------------------

    #[Test]
    public function save_rejects_empty_activities(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            ['activities' => []],
        );

        $response->assertJsonValidationErrors(['activities']);
    }

    #[Test]
    public function save_rejects_activity_with_no_sets(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [],
                    ],
                ],
            ],
        );

        $response->assertJsonValidationErrors(['activities.0.sets']);
    }

    #[Test]
    public function save_rejects_invalid_exercise_id(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => 99999,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertJsonValidationErrors(['activities.0.exercise_id']);
    }

    #[Test]
    public function save_rejects_activity_id_belonging_to_another_workout(): void
    {
        $user = User::factory()->create();
        $workout1 = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $workout2 = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $otherActivity = Activity::factory()->for($workout2, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout1->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $otherActivity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertJsonValidationErrors(['activities.0.id']);
    }

    #[Test]
    public function save_rejects_set_with_missing_required_fields(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => null, 'effort_value' => null, 'difficulty_value' => null],
                        ],
                    ],
                ],
            ],
        );

        $response->assertJsonValidationErrors([
            'activities.0.sets.0.order',
            'activities.0.sets.0.effort_value',
        ]);
    }

    #[Test]
    public function save_rejects_completed_set_with_zero_effort(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 0, 'difficulty_value' => 50, 'is_completed' => true],
                        ],
                    ],
                ],
            ],
        );

        $response->assertJsonValidationErrors(['activities.0.sets.0.is_completed']);
    }

    // -------------------------------------------------------
    // Heart-rate zone difficulty
    // -------------------------------------------------------

    private function zoneWorkout(User $user): array
    {
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $equipment = \App\Models\Equipment::factory()->heartRateZone()->create();
        $exercise = Exercise::factory()->create(['equipment_id' => $equipment->id]);

        return [$workout, $exercise];
    }

    #[Test]
    public function save_accepts_a_valid_heart_rate_zone(): void
    {
        $user = User::factory()->create();
        [$workout, $exercise] = $this->zoneWorkout($user);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 1800, 'difficulty_value' => 2],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();
        $this->assertDatabaseHas('sets', ['effort_value' => 1800, 'difficulty_value' => 2]);
    }

    #[Test]
    public function save_allows_null_zone_for_a_zone_exercise(): void
    {
        $user = User::factory()->create();
        [$workout, $exercise] = $this->zoneWorkout($user);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 300, 'difficulty_value' => null],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();
    }

    #[Test]
    public function save_rejects_a_zone_above_five(): void
    {
        $user = User::factory()->create();
        [$workout, $exercise] = $this->zoneWorkout($user);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 1800, 'difficulty_value' => 7],
                        ],
                    ],
                ],
            ],
        );

        $response->assertJsonValidationErrors(['activities.0.sets.0.difficulty_value']);
    }

    #[Test]
    public function save_rejects_a_zone_below_one(): void
    {
        $user = User::factory()->create();
        [$workout, $exercise] = $this->zoneWorkout($user);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 1800, 'difficulty_value' => 0],
                        ],
                    ],
                ],
            ],
        );

        $response->assertJsonValidationErrors(['activities.0.sets.0.difficulty_value']);
    }

    #[Test]
    public function save_allows_large_weight_for_non_zone_exercise(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 5, 'difficulty_value' => 120],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();
    }

    // -------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------

    #[Test]
    public function save_replaces_all_activities_with_new_ones(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();

        $oldActivity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise1->id,
            'order' => 1,
        ]);
        $oldSet = Set::factory()->for($oldActivity, 'activity')->create(['order' => 1]);

        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise2->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'effort_value' => 10, 'difficulty_value' => 100],
                        ],
                    ],
                ],
            ],
        );

        $response->assertOk();

        $this->assertDatabaseMissing('activities', ['id' => $oldActivity->id]);
        $this->assertDatabaseMissing('sets', ['id' => $oldSet->id]);
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseHas('activities', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
        ]);
    }

    #[Test]
    public function save_handles_set_id_belonging_to_different_activity_in_same_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity1 = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        $activity2 = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 2,
        ]);

        $set1 = Set::factory()->for($activity1, 'activity')->create(['order' => 1]);
        $set2 = Set::factory()->for($activity2, 'activity')->create(['order' => 1]);

        // Try to put set2 (belongs to activity2) into activity1's sets
        $response = $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            [
                'activities' => [
                    [
                        'id' => $activity1->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['id' => $set2->id, 'order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                    [
                        'id' => $activity2->id,
                        'exercise_id' => $exercise->id,
                        'order' => 2,
                        'sets' => [
                            ['id' => $set1->id, 'order' => 1, 'effort_value' => 5, 'difficulty_value' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function save_is_idempotent_when_sent_same_data_twice(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $activity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);

        $set = Set::factory()->for($activity, 'activity')->create([
            'order' => 1,
            'effort_value' => 5,
            'difficulty_value' => 50,
            'is_completed' => true,
        ]);

        $payload = [
            'activities' => [
                [
                    'id' => $activity->id,
                    'exercise_id' => $exercise->id,
                    'order' => 1,
                    'sets' => [
                        [
                            'id' => $set->id,
                            'order' => 1,
                            'effort_value' => 5,
                            'difficulty_value' => 50,
                            'is_completed' => true,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            $payload,
        )->assertOk();

        $this->actingAs($user)->patchJson(
            '/api/v1/workouts/'.$workout->id.'/save',
            $payload,
        )->assertOk();

        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('sets', 1);
        $this->assertDatabaseHas('sets', [
            'id' => $set->id,
            'effort_value' => 5,
            'difficulty_value' => 50,
        ]);
    }
}

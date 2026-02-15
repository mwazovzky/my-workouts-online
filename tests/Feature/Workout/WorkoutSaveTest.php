<?php

namespace Tests\Feature\Workout;

use App\Models\Activity;
use App\Models\Exercise;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
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
            'repetitions' => 5,
            'weight' => 50,
            'is_completed' => false,
        ]);

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
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
                                'repetitions' => 10,
                                'weight' => 80,
                                'is_completed' => true,
                            ],
                        ],
                    ],
                ],
            ],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('sets', [
            'id' => $set->id,
            'repetitions' => 10,
            'weight' => 80,
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['id' => $set1->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                            ['order' => 2, 'repetitions' => 8, 'weight' => 60],
                        ],
                    ],
                ],
            ],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('sets', ['id' => $set1->id]);
        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);
        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 2,
            'repetitions' => 8,
            'weight' => 60,
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'id' => $activity1->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 5, 'weight' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertRedirect();

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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise1->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 5, 'weight' => 50],
                        ],
                    ],
                    [
                        'exercise_id' => $exercise2->id,
                        'order' => 2,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 10, 'weight' => 100],
                        ],
                    ],
                ],
            ],
        );

        $response->assertRedirect();

        $this->assertDatabaseCount('activities', 2);
        $this->assertDatabaseHas('activities', [
            'workout_id' => $workout->id,
            'exercise_id' => $exercise2->id,
            'order' => 2,
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

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'repetitions' => 6, 'weight' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'repetitions' => 7, 'weight' => 70]);
        $set4 = Set::factory()->for($activity, 'activity')->create(['order' => 4, 'repetitions' => 8, 'weight' => 80]);

        // Keep set1 (updated), delete set2 and set4, keep set3, add two new
        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['id' => $set1->id, 'order' => 1, 'repetitions' => 12, 'weight' => 55],
                            ['id' => $set3->id, 'order' => 2, 'repetitions' => 7, 'weight' => 70],
                            ['order' => 3, 'repetitions' => 10, 'weight' => 100],
                            ['order' => 4, 'repetitions' => 11, 'weight' => 110],
                        ],
                    ],
                ],
            ],
        );

        $response->assertRedirect();

        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);
        $this->assertDatabaseMissing('sets', ['id' => $set4->id]);
        $this->assertDatabaseHas('sets', ['id' => $set1->id, 'order' => 1, 'repetitions' => 12, 'weight' => 55]);
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

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'repetitions' => 6, 'weight' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'repetitions' => 7, 'weight' => 70]);

        // Frontend sends already-normalized orders [1, 2] after deleting the middle set
        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['id' => $set1->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                            ['id' => $set3->id, 'order' => 2, 'repetitions' => 7, 'weight' => 70],
                        ],
                    ],
                ],
            ],
        );

        $response->assertRedirect();

        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);
        $this->assertDatabaseHas('sets', ['id' => $set1->id, 'order' => 1]);
        $this->assertDatabaseHas('sets', ['id' => $set3->id, 'order' => 2]);
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 5, 'weight' => 50],
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 5, 'weight' => 50],
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

        $response = $this->patch(
            route('workouts.save', ['workout' => $workout->id]),
            ['activities' => []],
        );

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function save_nonexistent_workout_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => 99999]),
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            ['activities' => []],
        );

        $response->assertSessionHasErrors(['activities']);
    }

    #[Test]
    public function save_rejects_activity_with_no_sets(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
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

        $response->assertSessionHasErrors(['activities.0.sets']);
    }

    #[Test]
    public function save_rejects_invalid_exercise_id(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'exercise_id' => 99999,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 5, 'weight' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertSessionHasErrors(['activities.0.exercise_id']);
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout1->id]),
            [
                'activities' => [
                    [
                        'id' => $otherActivity->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 5, 'weight' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertSessionHasErrors(['activities.0.id']);
    }

    #[Test]
    public function save_rejects_set_with_missing_required_fields(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => null, 'repetitions' => null, 'weight' => null],
                        ],
                    ],
                ],
            ],
        );

        $response->assertSessionHasErrors([
            'activities.0.sets.0.order',
            'activities.0.sets.0.repetitions',
            'activities.0.sets.0.weight',
        ]);
    }

    #[Test]
    public function save_rejects_completed_set_with_zero_repetitions(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 0, 'weight' => 50, 'is_completed' => true],
                        ],
                    ],
                ],
            ],
        );

        $response->assertSessionHasErrors(['activities.0.sets.0.is_completed']);
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'exercise_id' => $exercise2->id,
                        'order' => 1,
                        'sets' => [
                            ['order' => 1, 'repetitions' => 10, 'weight' => 100],
                        ],
                    ],
                ],
            ],
        );

        $response->assertRedirect();

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
        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            [
                'activities' => [
                    [
                        'id' => $activity1->id,
                        'exercise_id' => $exercise->id,
                        'order' => 1,
                        'sets' => [
                            ['id' => $set2->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                        ],
                    ],
                    [
                        'id' => $activity2->id,
                        'exercise_id' => $exercise->id,
                        'order' => 2,
                        'sets' => [
                            ['id' => $set1->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                        ],
                    ],
                ],
            ],
        );

        $response->assertSessionHasErrors();
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
            'repetitions' => 5,
            'weight' => 50,
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
                            'repetitions' => 5,
                            'weight' => 50,
                            'is_completed' => true,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            $payload,
        )->assertRedirect();

        $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            $payload,
        )->assertRedirect();

        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('sets', 1);
        $this->assertDatabaseHas('sets', [
            'id' => $set->id,
            'repetitions' => 5,
            'weight' => 50,
        ]);
    }
}

<?php

namespace Tests\Feature\Workout;

use App\Models\Activity;
use App\Models\Exercise;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_create_and_complete_return_an_etag(): void
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create();

        $created = $this->actingAs($user)->postJson('/api/v1/workouts', ['workout_template_id' => $template->id])
            ->assertCreated();
        $id = $created->json('data.id');

        $created->assertHeader('ETag', sprintf('"%d-1"', $id));
        $this->getJson("/api/v1/workouts/{$id}")->assertOk()->assertHeader('ETag', sprintf('"%d-1"', $id));
        $this->postJson("/api/v1/workouts/{$id}/complete")->assertOk()->assertHeader('ETag', sprintf('"%d-2"', $id));
    }

    #[Test]
    public function a_matching_if_match_saves_and_returns_a_new_etag(): void
    {
        [$user, $workout] = $this->workoutWithOneSet();
        $etag = $this->actingAs($user)->getJson("/api/v1/workouts/{$workout->id}")->headers->get('ETag');

        $response = $this->putJson("/api/v1/workouts/{$workout->id}/activities", $this->payload($workout, 7), ['If-Match' => $etag])
            ->assertOk();

        $this->assertNotSame($etag, $response->headers->get('ETag'));
        $response->assertHeader('ETag', sprintf('"%d-2"', $workout->id));
        $this->assertDatabaseHas('sets', ['effort_value' => 7]);
    }

    #[Test]
    public function a_stale_if_match_is_refused_and_nothing_is_written(): void
    {
        [$user, $workout] = $this->workoutWithOneSet();
        $stale = $this->actingAs($user)->getJson("/api/v1/workouts/{$workout->id}")->headers->get('ETag');

        $this->putJson("/api/v1/workouts/{$workout->id}/activities", $this->payload($workout, 7), ['If-Match' => $stale])->assertOk();

        $response = $this->putJson("/api/v1/workouts/{$workout->id}/activities", $this->payload($workout, 9), ['If-Match' => $stale]);

        $this->assertProblem($response, 412, 'precondition_failed');
        $this->assertDatabaseMissing('sets', ['effort_value' => 9]);
        $this->assertSame(2, $workout->fresh()->version);
    }

    #[Test]
    public function a_missing_if_match_is_refused(): void
    {
        [$user, $workout] = $this->workoutWithOneSet();

        $response = $this->actingAs($user)->putJson("/api/v1/workouts/{$workout->id}/activities", $this->payload($workout, 7));

        $this->assertProblem($response, 428, 'precondition_required');
        $this->assertSame(1, $workout->fresh()->version);
    }

    #[Test]
    public function a_weak_tag_never_matches(): void
    {
        [$user, $workout] = $this->workoutWithOneSet();

        $response = $this->actingAs($user)->putJson(
            "/api/v1/workouts/{$workout->id}/activities",
            $this->payload($workout, 7),
            ['If-Match' => sprintf('W/"%d-1"', $workout->id)],
        );

        $this->assertProblem($response, 412, 'precondition_failed');
    }

    #[Test]
    public function a_wildcard_or_a_list_containing_the_current_tag_matches(): void
    {
        [$user, $workout] = $this->workoutWithOneSet();

        $this->actingAs($user)
            ->putJson("/api/v1/workouts/{$workout->id}/activities", $this->payload($workout, 7), ['If-Match' => '*'])
            ->assertOk();

        $this->putJson(
            "/api/v1/workouts/{$workout->id}/activities",
            $this->payload($workout, 8),
            ['If-Match' => sprintf('"%d-1", "%d-2"', $workout->id, $workout->id)],
        )->assertOk()->assertHeader('ETag', sprintf('"%d-3"', $workout->id));
    }

    #[Test]
    public function ownership_and_status_are_checked_before_the_precondition(): void
    {
        [$owner, $workout] = $this->workoutWithOneSet();
        $other = User::factory()->create();

        $this->assertProblem(
            $this->actingAs($other)->putJson("/api/v1/workouts/{$workout->id}/activities", $this->payload($workout, 7)),
            404,
            'not_found',
        );

        $workout->update(['status' => 'completed']);

        $this->assertProblem(
            $this->actingAs($owner)->putJson("/api/v1/workouts/{$workout->id}/activities", $this->payload($workout, 7)),
            403,
            'forbidden',
        );
    }

    #[Test]
    public function the_old_save_endpoint_is_gone(): void
    {
        [$user, $workout] = $this->workoutWithOneSet();

        $this->assertProblem(
            $this->actingAs($user)->patchJson("/api/v1/workouts/{$workout->id}/save", $this->payload($workout, 7)),
            404,
            'not_found',
        );
    }

    /**
     * @return array{0: User, 1: Workout}
     */
    private function workoutWithOneSet(): array
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $activity = Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => Exercise::factory()->create()->id,
            'order' => 1,
        ]);
        Set::factory()->for($activity, 'activity')->create(['order' => 1, 'effort_value' => 5]);

        return [$user, $workout];
    }

    /**
     * @return array{activities: array<int, array<string, mixed>>}
     */
    private function payload(Workout $workout, int $effort): array
    {
        $activity = $workout->activities()->with('sets')->firstOrFail();

        return ['activities' => [[
            'id' => $activity->id,
            'exercise_id' => $activity->exercise_id,
            'order' => 1,
            'sets' => [['id' => $activity->sets->first()->id, 'order' => 1, 'effort_value' => $effort, 'difficulty_value' => null]],
        ]]];
    }
}

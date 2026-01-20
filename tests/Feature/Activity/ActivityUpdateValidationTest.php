<?php

namespace Tests\Feature\Activity;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityUpdateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_update_uses_custom_validation_messages(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $payload = [
            'sets' => [
                [
                    'order' => null,
                    'repetitions' => null,
                    'weight' => null,
                ],
            ],
        ];

        $response = $this
            ->actingAs($user)
            ->patch(route('activities.update', ['activity' => $activity->id]), $payload);

        $response->assertSessionHasErrors([
            'sets.0.order',
            'sets.0.repetitions',
            'sets.0.weight',
        ]);

        $this->assertSame(
            'Order is required for each set',
            session('errors')->get('sets.0.order')[0],
        );
        $this->assertSame(
            'Repetitions are required for each set',
            session('errors')->get('sets.0.repetitions')[0],
        );
        $this->assertSame(
            'Weight is required for each set',
            session('errors')->get('sets.0.weight')[0],
        );
    }
}

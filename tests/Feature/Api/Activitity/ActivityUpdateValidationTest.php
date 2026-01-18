<?php

namespace Tests\Feature\Api\Activitity;

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
            ->patchJson(route('api.activities.update', ['activity' => $activity->id]), $payload);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'sets.0.order',
            'sets.0.repetitions',
            'sets.0.weight',
        ]);

        $json = $response->json('errors');

        $this->assertSame('Order is required for each set', $json['sets.0.order'][0]);
        $this->assertSame('Repetitions are required for each set', $json['sets.0.repetitions'][0]);
        $this->assertSame('Weight is required for each set', $json['sets.0.weight'][0]);
    }
}

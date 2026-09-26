<?php

namespace Tests\Feature\Api;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unauthenticated(): void
    {
        $this->getJson('/api/v1/workouts')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);
    }

    #[Test]
    public function forbidden_by_role_and_by_policy(): void
    {
        $user = User::factory()->create();
        $foreign = Workout::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/admin/equipment')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this->actingAs($user)->deleteJson("/api/v1/workouts/{$foreign->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->actingAs($user)->getJson("/api/v1/workouts/{$foreign->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Record not found.', 'code' => 'not_found']);
    }

    #[Test]
    public function not_found_for_missing_records_and_unknown_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/programs/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Record not found.', 'code' => 'not_found']);

        $this->actingAs($user)->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    #[Test]
    public function conflict_from_reference_guard_uses_its_own_code(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::factory()->create();
        Exercise::factory()->create(['equipment_id' => $equipment->id]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/equipment/{$equipment->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'in_use')
            ->assertJsonStructure(['message', 'code']);
    }

    #[Test]
    public function validation_errors_carry_rule_codes_per_field_including_nested_paths(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson("/api/v1/workouts/{$workout->id}/save", [
            'activities' => [[
                'exercise_id' => $exercise->id,
                'order' => 1,
                'sets' => [
                    ['order' => 1, 'effort_value' => 5],
                    ['order' => 2, 'effort_value' => 'many'],
                ],
            ]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['message', 'code', 'errors' => ['activities.0.sets.1.effort_value' => [['code', 'message']]]]);

        $this->assertSame('integer', $response->json('errors')['activities.0.sets.1.effort_value'][0]['code']);
    }

    #[Test]
    public function validation_codes_cover_common_rules(): void
    {
        $existing = User::factory()->create();

        $errors = $this->postJson('/api/v1/auth/register', [
            'email' => $existing->email,
            'password' => 'short',
            'password_confirmation' => 'other',
            'client' => 'toaster',
        ])->assertUnprocessable()->json('errors');

        $this->assertSame('required', $errors['name'][0]['code']);
        $this->assertSame('unique', $errors['email'][0]['code']);
        $this->assertSame('enum', $errors['client'][0]['code']);
        $this->assertContains($errors['password'][0]['code'], ['confirmed', 'password', 'min']);
    }

    #[Test]
    public function manual_validation_messages_use_the_invalid_code(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong', 'client' => 'web'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0.code', 'invalid');
    }

    #[Test]
    public function too_many_requests(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear('resend');

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->postJson('/api/v1/auth/email/resend');
        }

        $this->actingAs($user)->postJson('/api/v1/auth/email/resend')
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }
}

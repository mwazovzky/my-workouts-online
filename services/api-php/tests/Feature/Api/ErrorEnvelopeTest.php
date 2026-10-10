<?php

namespace Tests\Feature\Api;

use App\Enums\FieldErrorCode;
use App\Http\RequestId;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The RFC 9457 Problem body every non-2xx API response uses.
 */
class ErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unauthenticated_with_and_without_a_json_accept_header(): void
    {
        $this->getJson('/api/v1/workouts')
            ->assertProblem(401, 'unauthenticated')
            ->assertJsonPath('title', 'Unauthenticated')
            ->assertJsonPath('detail', 'Unauthenticated.');

        $this->get('/api/v1/workouts')->assertProblem(401, 'unauthenticated');
    }

    #[Test]
    public function an_expired_access_token_carries_its_own_code(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->subMinutes(30));
        $token = app(TokenServiceInterface::class)->issuePair($user, 'test')->accessToken;
        $this->travelBack();

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertProblem(401, 'token_expired')
            ->assertJsonPath('title', 'Access token expired');
    }

    #[Test]
    public function forbidden_by_role_and_by_verification_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/admin/equipment')
            ->assertProblem(403, 'forbidden')
            ->assertJsonPath('detail', 'This action is unauthorized.');

        $this->actingAs(User::factory()->unverified()->create())->getJson('/api/v1/dashboard')
            ->assertProblem(403, 'email_unverified')
            ->assertJsonPath('title', 'Email address not verified');
    }

    #[Test]
    public function not_found_for_missing_records_and_unknown_routes(): void
    {
        $user = User::factory()->create();
        $foreign = Workout::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/programs/999999')
            ->assertProblem(404, 'not_found')
            ->assertJsonPath('detail', 'Record not found.');

        $this->actingAs($user)->getJson("/api/v1/workouts/{$foreign->id}")
            ->assertProblem(404, 'not_found');

        $this->getJson('/api/v1/does-not-exist')
            ->assertProblem(404, 'not_found')
            ->assertJsonPath('instance', fn (string $instance) => str_starts_with($instance, '/api/v1/does-not-exist#'));
    }

    #[Test]
    public function method_not_allowed(): void
    {
        $this->actingAs(User::factory()->create())->deleteJson('/api/v1/programs')
            ->assertProblem(405, 'method_not_allowed');
    }

    #[Test]
    public function conflict_from_a_reference_guard_uses_its_own_code(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::factory()->create();
        Exercise::factory()->create(['equipment_id' => $equipment->id]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/equipment/{$equipment->id}")
            ->assertProblem(409, 'in_use')
            ->assertJsonPath('title', 'Resource in use');
    }

    #[Test]
    public function validation_errors_are_a_list_of_pointers_with_field_codes(): void
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

        $response->assertFieldErrors(['/activities/0/sets/1/effort_value'], 'invalid_format');
        $this->assertSame(
            ['pointer' => '/activities/0/sets/1/effort_value', 'code' => 'invalid_format', 'detail' => 'The effort value field must be an integer.'],
            $response->fieldError('activities.0.sets.1.effort_value'),
        );
        $this->assertStringStartsWith('The effort value', $response->json('detail'));
    }

    #[Test]
    public function common_rules_map_to_the_field_error_codes(): void
    {
        $existing = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => $existing->email,
            'password' => 'short',
            'password_confirmation' => 'other',
            'client' => 'toaster',
        ]);

        $response->assertFieldErrors(['name'], 'required');
        $response->assertFieldErrors(['email'], 'not_unique');
        $response->assertFieldErrors(['client'], 'invalid_value');
        $this->assertContains($response->fieldError('password')['code'], ['mismatch', 'invalid_format', 'too_short']);
    }

    #[Test]
    public function every_validation_rule_used_by_the_app_maps_to_a_named_code(): void
    {
        $rules = [
            'required', 'present', 'string', 'email', 'integer', 'numeric', 'boolean', 'array', 'lowercase',
            'min', 'max', 'in', 'enum', 'exists', 'unique', 'distinct', 'confirmed', 'current_password',
            'password', 'nullable', 'sometimes', 'bail', 'associative_array', 'heart_rate_zone_within_range',
            'completed_set_requires_effort', 'unknown_field',
        ];

        $neverFails = ['nullable', 'sometimes', 'bail'];

        foreach (array_diff($rules, $neverFails) as $rule) {
            $this->assertInstanceOf(FieldErrorCode::class, FieldErrorCode::fromRule($rule, ['string']), $rule);
        }

        $this->assertSame(FieldErrorCode::TooShort, FieldErrorCode::fromRule('min', ['required', 'string', 'max:255']));
        $this->assertSame(FieldErrorCode::OutOfRange, FieldErrorCode::fromRule('min', ['required', 'integer']));
        $this->assertSame(FieldErrorCode::OutOfRange, FieldErrorCode::fromRule('heart_rate_zone_within_range', []));
        $this->assertSame(FieldErrorCode::UnknownField, FieldErrorCode::fromRule('unknown_field', []));
    }

    #[Test]
    public function manual_validation_messages_use_the_invalid_value_code(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong', 'client' => 'web'])
            ->assertFieldErrors(['email'], 'invalid_value');
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
            ->assertProblem(429, 'too_many_requests')
            ->assertHeader('Retry-After');
    }

    #[Test]
    public function unhandled_exceptions_become_an_internal_error_problem(): void
    {
        Route::middleware('api')->get('/api/v1/boom', static function (): never {
            throw new RuntimeException('kaboom');
        });

        config(['app.debug' => false]);

        $this->getJson('/api/v1/boom')
            ->assertProblem(500, 'internal_error')
            ->assertJsonPath('title', 'Internal server error')
            ->assertJsonPath('detail', 'Server Error');
    }

    #[Test]
    public function the_request_id_is_echoed_on_success_and_honoured_when_supplied(): void
    {
        $user = User::factory()->create();

        $generated = $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->headers->get(RequestId::HEADER);
        $this->assertTrue(Str::isUuid((string) $generated));

        $supplied = (string) Str::uuid();
        $this->actingAs($user)->withHeader(RequestId::HEADER, $supplied)->getJson('/api/v1/me')
            ->assertOk()
            ->assertHeader(RequestId::HEADER, $supplied);
    }

    #[Test]
    public function a_problem_carries_the_supplied_request_id_and_replaces_an_invalid_one(): void
    {
        $supplied = (string) Str::uuid();

        $this->withHeader(RequestId::HEADER, $supplied)->getJson('/api/v1/me')
            ->assertProblem(401, 'unauthenticated')
            ->assertHeader(RequestId::HEADER, $supplied)
            ->assertJsonPath('instance', '/api/v1/me#'.$supplied);

        $replaced = $this->withHeader(RequestId::HEADER, 'not-a-uuid')->getJson('/api/v1/me')
            ->assertProblem(401, 'unauthenticated')
            ->headers->get(RequestId::HEADER);

        $this->assertNotSame('not-a-uuid', $replaced);
        $this->assertTrue(Str::isUuid((string) $replaced));
    }

    #[Test]
    public function the_type_uri_points_at_the_problems_registry_under_the_app_url(): void
    {
        config(['app.url' => 'https://api.example.test/']);

        $this->getJson('/api/v1/me')
            ->assertProblem(401, 'unauthenticated')
            ->assertJsonPath('type', 'https://api.example.test/problems/unauthenticated');
    }
}

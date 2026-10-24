<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * String `max:N` rules count the value as sent, before TrimStrings, like the
 * contract's `maxLength` and the Go API.
 */
class UntrimmedLengthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_name_over_the_limit_only_before_trimming_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/equipment', [
            'translations' => ['en' => ['name' => "\n".str_repeat('*', 255)]],
            'difficulty_unit' => 'pounds',
        ]);

        $response->assertFieldErrors(['translations.en.name'], 'too_long');
        $this->assertSame(
            'The translations.en.name field must not be greater than 255 characters.',
            $response->fieldError('translations.en.name')['detail'],
        );
        $this->assertDatabaseCount('equipment', 0);
    }

    #[Test]
    public function a_top_level_string_over_the_limit_before_trimming_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => ' '.str_repeat('a', 255),
            'email' => 'new@example.com',
            'password' => 'password-123',
            'client' => 'web',
        ]);

        $response->assertFieldErrors(['name'], 'too_long');
        $this->assertCount(1, $response->json('errors'));
    }

    #[Test]
    public function padded_values_within_the_limit_are_still_trimmed_and_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/equipment', [
            'translations' => ['en' => ['name' => '  '.str_repeat('*', 253)]],
            'difficulty_unit' => 'pounds',
        ])->assertCreated();

        $this->assertDatabaseHas('equipment', ['name' => str_repeat('*', 253)]);
    }

    #[Test]
    public function a_value_over_the_limit_after_trimming_reports_one_error(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/equipment', [
            'translations' => ['en' => ['name' => str_repeat('*', 256)]],
            'difficulty_unit' => 'pounds',
        ]);

        $response->assertFieldErrors(['translations.en.name'], 'too_long');
        $this->assertCount(1, $response->json('errors'));
    }

    #[Test]
    public function numeric_max_rules_are_left_to_the_regular_validation(): void
    {
        $user = User::factory()->create();
        WorkoutTemplate::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/workouts?per_page=100')->assertOk();
    }
}

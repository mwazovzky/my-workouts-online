<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnknownKeysTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_unknown_top_level_key_is_rejected_with_the_unknown_field_code(): void
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/workouts', [
            'workout_template_id' => $template->id,
            'workout_tempate_id' => $template->id,
        ]);

        $response->assertFieldErrors(['workout_tempate_id'], 'unknown_field');
        $this->assertSame('This field is not allowed.', $response->fieldError('workout_tempate_id')['detail']);
    }

    #[Test]
    public function a_nested_unknown_key_is_reported_on_its_full_pointer(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson("/api/v1/workouts/{$workout->id}/save", [
            'activities' => [[
                'exercise_id' => $exercise->id,
                'order' => 1,
                'sets' => [
                    ['order' => 1, 'effort_value' => 5, 'weight' => 40],
                ],
            ]],
        ]);

        $response->assertFieldErrors(['/activities/0/sets/0/weight'], 'unknown_field');
        $this->assertCount(1, $response->json('errors'));
    }

    #[Test]
    public function an_unknown_key_inside_translations_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/categories', [
            'translations' => [
                'en' => ['name' => 'Chest', 'title' => 'Chest'],
            ],
        ]);

        $response->assertFieldErrors(['translations.en.title'], 'unknown_field');
        $this->assertCount(1, $response->json('errors'));
    }

    #[Test]
    public function inline_validations_converted_to_form_requests_reject_unknown_keys(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $pair = app(TokenServiceInterface::class)->issuePair($user, 'test');

        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'secret123',
            'password' => 'new-password-123',
            'remember' => true,
        ])->assertFieldErrors(['remember'], 'unknown_field');

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $pair->refreshToken,
            'client' => 'native',
        ])->assertFieldErrors(['client'], 'unknown_field');
    }

    #[Test]
    public function the_unknown_field_message_is_localized(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('Accept-Language', 'ru')
            ->getJson('/api/v1/workouts?page=1&sort=name');

        $response->assertFieldErrors(['sort'], 'unknown_field');
        $this->assertSame('Это поле не допускается.', $response->fieldError('sort')['detail']);
    }

    #[Test]
    public function omitted_optional_keys_are_not_flagged(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'long-enough-password',
            'client' => 'native',
        ])->assertCreated();
    }

    #[Test]
    public function a_list_of_scalars_declared_with_a_wildcard_is_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $categories = Category::factory()->count(2)->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/exercises', [
            'equipment_id' => Equipment::factory()->create()->id,
            'effort_type' => 'repetitions',
            'rest_time_seconds' => 90,
            'category_ids' => $categories->pluck('id')->all(),
            'translations' => [
                'en' => ['name' => 'Bench Press', 'description' => 'Chest exercise.'],
                'ru' => ['name' => 'Жим лёжа', 'description' => 'Упражнение на грудь.'],
            ],
        ])->assertCreated();
    }
}

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
    public function an_unknown_top_level_key_is_rejected_with_the_invalid_code(): void
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/workouts', [
            'workout_template_id' => $template->id,
            'workout_tempate_id' => $template->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.workout_tempate_id.0.code', 'invalid')
            ->assertJsonPath('errors.workout_tempate_id.0.message', 'This field is not allowed.');
    }

    #[Test]
    public function a_nested_unknown_key_is_reported_on_its_full_path(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create();

        $errors = $this->actingAs($user)->patchJson("/api/v1/workouts/{$workout->id}/save", [
            'activities' => [[
                'exercise_id' => $exercise->id,
                'order' => 1,
                'sets' => [
                    ['order' => 1, 'effort_value' => 5, 'weight' => 40],
                ],
            ]],
        ])->assertUnprocessable()->json('errors');

        $this->assertSame(['activities.0.sets.0.weight'], array_keys($errors));
        $this->assertSame('invalid', $errors['activities.0.sets.0.weight'][0]['code']);
    }

    #[Test]
    public function an_unknown_key_inside_translations_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $errors = $this->actingAs($admin)->postJson('/api/v1/admin/categories', [
            'translations' => [
                'en' => ['name' => 'Chest', 'title' => 'Chest'],
            ],
        ])->assertUnprocessable()->json('errors');

        $this->assertSame(['translations.en.title'], array_keys($errors));
        $this->assertSame('invalid', $errors['translations.en.title'][0]['code']);
    }

    #[Test]
    public function inline_validations_converted_to_form_requests_reject_unknown_keys(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $pair = app(TokenServiceInterface::class)->issuePair($user, 'test');

        $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'secret123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'remember' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.remember.0.code', 'invalid');

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $pair->refreshToken,
            'client' => 'native',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.client.0.code', 'invalid');
    }

    #[Test]
    public function omitted_optional_keys_and_confirmation_fields_are_not_flagged(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'long-enough-password',
            'password_confirmation' => 'long-enough-password',
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

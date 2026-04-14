<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidationLocaleTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // Standard Laravel validation messages
    // -------------------------------------------------------

    #[Test]
    public function standard_validation_error_appears_in_russian_for_russian_user(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();

        $errors = $response->json('errors');
        $this->assertStringContainsString('обязательно', $errors['name'][0]);
    }

    #[Test]
    public function standard_validation_error_appears_in_english_for_english_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();

        $errors = $response->json('errors');
        $this->assertStringContainsString('required', $errors['name'][0]);
    }

    // -------------------------------------------------------
    // WorkoutStoreRequest custom messages
    // -------------------------------------------------------

    #[Test]
    public function workout_store_required_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->postJson('/api/v1/workouts', []);

        $response->assertUnprocessable();

        $this->assertSame(
            'Для начала тренировки необходимо выбрать шаблон.',
            $response->json('errors.workout_template_id.0'),
        );
    }

    #[Test]
    public function workout_store_exists_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->postJson('/api/v1/workouts', [
            'workout_template_id' => 9999,
        ]);

        $response->assertUnprocessable();

        $this->assertSame(
            'Выбранный шаблон тренировки не найден.',
            $response->json('errors.workout_template_id.0'),
        );
    }

    #[Test]
    public function workout_store_required_error_in_english(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->postJson('/api/v1/workouts', []);

        $response->assertUnprocessable();

        $this->assertSame(
            'A workout template is required to start a workout.',
            $response->json('errors.workout_template_id.0'),
        );
    }

    // -------------------------------------------------------
    // WorkoutSaveRequest custom messages
    // -------------------------------------------------------

    #[Test]
    public function workout_save_empty_activities_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/workouts/{$workout->id}/save",
            ['activities' => []],
        );

        $response->assertUnprocessable();
        $this->assertStringContainsString('обязательно', $response->json('errors.activities.0'));
    }

    #[Test]
    public function workout_save_empty_sets_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/workouts/{$workout->id}/save",
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

        $response->assertUnprocessable();
        $errors = $response->json('errors');
        $this->assertStringContainsString('обязательно', $errors['activities.0.sets'][0]);
    }

    // -------------------------------------------------------
    // CompletedSetRequiresEffort custom rule
    // -------------------------------------------------------

    #[Test]
    public function completed_set_zero_effort_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/workouts/{$workout->id}/save",
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

        $response->assertUnprocessable();

        $errors = $response->json('errors');
        $this->assertSame(
            'Подход не может быть отмечен как выполненный с нулевым усилием.',
            $errors['activities.0.sets.0.is_completed'][0],
        );
    }

    #[Test]
    public function completed_set_zero_effort_error_in_english(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/workouts/{$workout->id}/save",
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

        $response->assertUnprocessable();

        $errors = $response->json('errors');
        $this->assertSame(
            'A set cannot be marked as completed with 0 effort.',
            $errors['activities.0.sets.0.is_completed'][0],
        );
    }

    // -------------------------------------------------------
    // Auth messages
    // -------------------------------------------------------

    #[Test]
    public function login_failed_message_uses_app_locale(): void
    {
        $response = $this
            ->withSession(['locale' => 'ru'])
            ->post('/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors(['email']);

        $this->assertSame(
            'Неверный адрес электронной почты или пароль.',
            session('errors')->get('email')[0],
        );
    }

    // -------------------------------------------------------
    // ProfileLocaleRequest attributes translation
    // -------------------------------------------------------

    #[Test]
    public function profile_locale_validation_uses_translated_attribute_name(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->patchJson('/api/v1/profile/locale', [
            'locale' => 'fr',
        ]);

        $response->assertUnprocessable();

        $errors = $response->json('errors');
        $this->assertStringContainsString('Язык', $errors['locale'][0]);
    }
}

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

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->patchJson('/api/v1/me', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('должно иметь значение', $response->fieldError('name')['detail']);
    }

    #[Test]
    public function standard_validation_error_appears_in_english_for_english_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->patchJson('/api/v1/me', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('must have a value', $response->fieldError('name')['detail']);
    }

    // -------------------------------------------------------
    // WorkoutStoreRequest custom messages
    // -------------------------------------------------------

    #[Test]
    public function workout_store_required_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->postJson('/api/v1/workouts', []);

        $response->assertUnprocessable();

        $this->assertSame(
            'Для начала тренировки необходимо выбрать шаблон.',
            $response->fieldError('workout_template_id')['detail'],
        );
    }

    #[Test]
    public function workout_store_exists_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->postJson('/api/v1/workouts', [
            'workout_template_id' => 9999,
        ]);

        $response->assertUnprocessable();

        $this->assertSame(
            'Выбранный шаблон тренировки не найден.',
            $response->fieldError('workout_template_id')['detail'],
        );
    }

    #[Test]
    public function workout_store_required_error_in_english(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->postJson('/api/v1/workouts', []);

        $response->assertUnprocessable();

        $this->assertSame(
            'A workout template is required to start a workout.',
            $response->fieldError('workout_template_id')['detail'],
        );
    }

    // -------------------------------------------------------
    // ReplaceWorkoutActivitiesRequest custom messages
    // -------------------------------------------------------

    #[Test]
    public function workout_save_empty_activities_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->putActivities(
            $workout->id,
            ['activities' => []],
        );

        $response->assertUnprocessable();
        $this->assertStringContainsString('обязательно', $response->fieldError('activities')['detail']);
    }

    #[Test]
    public function workout_save_empty_sets_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->putActivities(
            $workout->id,
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
        $this->assertStringContainsString('обязательно', $response->fieldError('activities.0.sets')['detail']);
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

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->putActivities(
            $workout->id,
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
        $this->assertSame(
            'Подход не может быть отмечен как выполненный с нулевым усилием.',
            $response->fieldError('activities.0.sets.0.is_completed')['detail'],
        );
    }

    #[Test]
    public function completed_set_zero_effort_error_in_english(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->putActivities(
            $workout->id,
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
        $this->assertSame(
            'A set cannot be marked as completed with 0 effort.',
            $response->fieldError('activities.0.sets.0.is_completed')['detail'],
        );
    }

    // -------------------------------------------------------
    // Auth messages
    // -------------------------------------------------------

    // -------------------------------------------------------
    // MeUpdateRequest attributes translation
    // -------------------------------------------------------

    #[Test]
    public function locale_validation_uses_translated_attribute_name(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->withHeader('Accept-Language', $user->locale)->patchJson('/api/v1/me', [
            'locale' => 'fr',
        ]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('Язык', $response->fieldError('locale')['detail']);
    }
}

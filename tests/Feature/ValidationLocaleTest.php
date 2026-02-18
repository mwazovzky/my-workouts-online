<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
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

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['name', 'email']);

        $errors = session('errors');
        $this->assertStringContainsString('обязательно', $errors->get('name')[0]);
    }

    #[Test]
    public function standard_validation_error_appears_in_english_for_english_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['name']);

        $errors = session('errors');
        $this->assertStringContainsString('required', $errors->get('name')[0]);
    }

    // -------------------------------------------------------
    // WorkoutStoreRequest custom messages
    // -------------------------------------------------------

    #[Test]
    public function workout_store_required_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->post(route('workouts.store'), []);

        $response->assertSessionHasErrors(['workout_template_id']);

        $this->assertSame(
            'Для начала тренировки необходимо выбрать шаблон.',
            session('errors')->get('workout_template_id')[0],
        );
    }

    #[Test]
    public function workout_store_exists_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'workout_template_id' => 9999,
        ]);

        $response->assertSessionHasErrors(['workout_template_id']);

        $this->assertSame(
            'Выбранный шаблон тренировки не найден.',
            session('errors')->get('workout_template_id')[0],
        );
    }

    #[Test]
    public function workout_store_required_error_in_english(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->post(route('workouts.store'), []);

        $response->assertSessionHasErrors(['workout_template_id']);

        $this->assertSame(
            'A workout template is required to start a workout.',
            session('errors')->get('workout_template_id')[0],
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
            ['activities' => []],
        );

        $response->assertSessionHasErrors(['activities']);

        $errors = session('errors');
        $this->assertStringContainsString('обязательно', $errors->get('activities')[0]);
    }

    #[Test]
    public function workout_save_empty_sets_error_in_russian(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
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

        $response->assertSessionHasErrors(['activities.0.sets']);

        $errors = session('errors');
        $this->assertStringContainsString('обязательно', $errors->get('activities.0.sets')[0]);
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

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
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

        $response->assertSessionHasErrors(['activities.0.sets.0.is_completed']);

        $this->assertSame(
            'Подход не может быть отмечен как выполненный с нулевым усилием.',
            session('errors')->get('activities.0.sets.0.is_completed')[0],
        );
    }

    #[Test]
    public function completed_set_zero_effort_error_in_english(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->patch(
            route('workouts.save', ['workout' => $workout->id]),
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

        $response->assertSessionHasErrors(['activities.0.sets.0.is_completed']);

        $this->assertSame(
            'A set cannot be marked as completed with 0 effort.',
            session('errors')->get('activities.0.sets.0.is_completed')[0],
        );
    }

    // -------------------------------------------------------
    // Auth messages
    // -------------------------------------------------------

    #[Test]
    public function login_failed_message_uses_app_locale(): void
    {
        App::setLocale('ru');

        $response = $this->post('/login', [
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

        $response = $this->actingAs($user)->patch('/profile/locale', [
            'locale' => 'fr',
        ]);

        $response->assertSessionHasErrors('locale');

        $errors = session('errors');
        $this->assertStringContainsString('Язык', $errors->get('locale')[0]);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // GET /api/v1/exercises
    // -------------------------------------------------------

    #[Test]
    public function index_returns_exercises_with_equipment_and_categories(): void
    {
        $user = User::factory()->create();
        $equipment = Equipment::factory()->withTranslation('name', 'Barbell')->create();
        $category = Category::factory()->withTranslation('name', 'Chest')->create();

        $exercise = Exercise::factory()
            ->withTranslation('name', 'Bench Press')
            ->for($equipment)
            ->create(['rest_time_seconds' => 90]);
        $exercise->categories()->attach($category->id);

        $response = $this->actingAs($user)->getJson('/api/v1/exercises');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $data = $response->json('data.0');
        $this->assertSame('Bench Press', $data['name']);
        $this->assertSame('Barbell', $data['equipment_name']);
        $this->assertSame('kilograms', $data['difficulty_unit']);
        $this->assertSame('repetitions', $data['effort_type']);
        $this->assertSame(90, $data['rest_time_seconds']);
        $this->assertSame(['Chest'], array_column($data['categories'], 'name'));
    }

    #[Test]
    public function index_excludes_retired_exercises(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->withTranslation('name', 'Bench Press')->create();
        $retired = Exercise::factory()->withTranslation('name', 'Upright Row')->create();

        $retired->delete();

        $response = $this->actingAs($user)->getJson('/api/v1/exercises');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame('Bench Press', $response->json('data.0.name'));
    }

    #[Test]
    public function index_sorts_exercises_by_name(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->withTranslation('name', 'Squat')->create();
        Exercise::factory()->withTranslation('name', 'Bench Press')->create();
        Exercise::factory()->withTranslation('name', 'deadlift')->create();

        $response = $this->actingAs($user)->getJson('/api/v1/exercises');

        $response->assertOk();
        $this->assertSame(
            ['Bench Press', 'deadlift', 'Squat'],
            array_column($response->json('data'), 'name'),
        );
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/v1/exercises')->assertUnauthorized();
    }
}

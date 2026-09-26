<?php

namespace Tests\Feature\Admin;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EquipmentAdminTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'difficulty_unit' => 'kilograms',
            'translations' => [
                'en' => ['name' => 'Barbell'],
                'ru' => ['name' => 'Штанга'],
            ],
        ], $overrides);
    }

    #[Test]
    public function non_admin_cannot_list_equipment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/admin/equipment')->assertForbidden();
    }

    #[Test]
    public function guest_cannot_list_equipment(): void
    {
        $this->getJson('/api/v1/admin/equipment')->assertUnauthorized();
    }

    #[Test]
    public function admin_can_list_equipment(): void
    {
        $admin = User::factory()->admin()->create();
        Equipment::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/equipment');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [['id', 'name', 'difficulty_unit', 'translations' => ['name' => ['en', 'ru']]]],
        ]);
    }

    #[Test]
    public function admin_can_show_equipment(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::factory()->create(['difficulty_unit' => 'kilograms']);

        $this->actingAs($admin)->getJson("/api/v1/admin/equipment/{$equipment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $equipment->id)
            ->assertJsonPath('data.difficulty_unit', 'kilograms')
            ->assertJsonStructure(['data' => ['id', 'name', 'difficulty_unit', 'translations' => ['name' => ['en', 'ru']]]]);
    }

    #[Test]
    public function non_admin_cannot_show_equipment(): void
    {
        $equipment = Equipment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/admin/equipment/{$equipment->id}")
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_create_equipment_with_translations(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/equipment', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.difficulty_unit', 'kilograms');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('equipment', ['id' => $id, 'difficulty_unit' => 'kilograms']);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'equipment', 'translatable_id' => $id,
            'locale' => 'en', 'field' => 'name', 'value' => 'Barbell',
        ]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'equipment', 'translatable_id' => $id,
            'locale' => 'ru', 'field' => 'name', 'value' => 'Штанга',
        ]);
    }

    #[Test]
    public function non_admin_cannot_create_equipment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/equipment', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('equipment', 0);
    }

    #[Test]
    public function create_requires_english_name(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/equipment', $this->validPayload([
            'translations' => ['en' => ['name' => ''], 'ru' => ['name' => 'Штанга']],
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrorFor('translations.en.name');
    }

    #[Test]
    public function create_rejects_invalid_difficulty_unit(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/equipment', $this->validPayload(['difficulty_unit' => 'stones']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('difficulty_unit');
    }

    #[Test]
    public function admin_can_update_equipment_and_translations(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::createWithTranslations(
            ['en' => ['name' => 'Barbell'], 'ru' => ['name' => 'Штанга']],
            ['difficulty_unit' => 'kilograms'],
        );

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/equipment/{$equipment->id}", [
            'difficulty_unit' => 'pounds',
            'translations' => ['en' => ['name' => 'Dumbbell'], 'ru' => ['name' => 'Гантель']],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'difficulty_unit' => 'pounds']);
        $this->assertDatabaseHas('translations', [
            'translatable_id' => $equipment->id, 'locale' => 'en', 'field' => 'name', 'value' => 'Dumbbell',
        ]);
    }

    #[Test]
    public function update_removing_russian_falls_back_to_english(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::createWithTranslations(
            ['en' => ['name' => 'Barbell'], 'ru' => ['name' => 'Штанга']],
            ['difficulty_unit' => 'kilograms'],
        );

        $this->actingAs($admin)->putJson("/api/v1/admin/equipment/{$equipment->id}", [
            'difficulty_unit' => 'kilograms',
            'translations' => ['en' => ['name' => 'Barbell'], 'ru' => ['name' => '']],
        ])->assertOk();

        $this->assertDatabaseMissing('translations', [
            'translatable_id' => $equipment->id, 'locale' => 'ru', 'field' => 'name',
        ]);
    }

    #[Test]
    public function admin_can_delete_unused_equipment(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/equipment/{$equipment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('equipment', ['id' => $equipment->id]);
    }

    #[Test]
    public function cannot_delete_equipment_in_use_by_exercises(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::factory()->create();
        Exercise::factory()->create(['equipment_id' => $equipment->id]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/equipment/{$equipment->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);
    }

    #[Test]
    public function cannot_delete_equipment_referenced_only_by_a_retired_exercise(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::factory()->create();
        $exercise = Exercise::factory()->create(['equipment_id' => $equipment->id]);
        $exercise->delete(); // soft delete — row still references the equipment

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/equipment/{$equipment->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);
    }
}

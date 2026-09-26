<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'translations' => [
                'en' => ['name' => 'Chest'],
                'ru' => ['name' => 'Грудь'],
            ],
        ], $overrides);
    }

    #[Test]
    public function non_admin_cannot_list_categories(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/categories')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_list_categories(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->count(3)->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/categories')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'translations' => ['name' => ['en', 'ru']]]]]);
    }

    #[Test]
    public function admin_can_show_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::createWithTranslations(['en' => ['name' => 'Chest'], 'ru' => ['name' => 'Грудь']]);

        $this->actingAs($admin)->getJson("/api/v1/admin/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.translations.name.ru', 'Грудь')
            ->assertJsonStructure(['data' => ['id', 'name', 'translations' => ['name' => ['en', 'ru']]]]);
    }

    #[Test]
    public function non_admin_cannot_show_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/admin/categories/{$category->id}")
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_create_category(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', $this->validPayload());

        $response->assertCreated();
        $id = $response->json('data.id');
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'category', 'translatable_id' => $id,
            'locale' => 'ru', 'field' => 'name', 'value' => 'Грудь',
        ]);
    }

    #[Test]
    public function non_admin_cannot_create_category(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/admin/categories', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('categories', 0);
    }

    #[Test]
    public function create_requires_english_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/categories', $this->validPayload([
            'translations' => ['en' => ['name' => '']],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('translations.en.name');
    }

    #[Test]
    public function admin_can_update_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::createWithTranslations(['en' => ['name' => 'Chest']]);

        $this->actingAs($admin)->putJson("/api/v1/admin/categories/{$category->id}", [
            'translations' => ['en' => ['name' => 'Back'], 'ru' => ['name' => 'Спина']],
        ])->assertOk();

        $this->assertDatabaseHas('translations', [
            'translatable_id' => $category->id, 'locale' => 'en', 'field' => 'name', 'value' => 'Back',
        ]);
    }

    #[Test]
    public function admin_can_delete_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/categories/{$category->id}")->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}

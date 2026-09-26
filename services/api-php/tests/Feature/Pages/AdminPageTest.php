<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function indexPages(): array
    {
        return [
            'dashboard' => ['admin.index', 'Admin/Index'],
            'equipment' => ['admin.equipment', 'Admin/EquipmentIndex'],
            'categories' => ['admin.categories', 'Admin/CategoryIndex'],
            'exercises' => ['admin.exercises', 'Admin/ExerciseIndex'],
            'workout templates' => ['admin.workout-templates', 'Admin/WorkoutTemplateIndex'],
            'programs' => ['admin.programs', 'Admin/ProgramIndex'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function createPages(): array
    {
        return [
            'equipment' => ['admin.equipment.create', 'Admin/EquipmentCreate'],
            'categories' => ['admin.categories.create', 'Admin/CategoryCreate'],
            'exercises' => ['admin.exercises.create', 'Admin/ExerciseCreate'],
            'workout templates' => ['admin.workout-templates.create', 'Admin/WorkoutTemplateCreate'],
            'programs' => ['admin.programs.create', 'Admin/ProgramCreate'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function editPages(): array
    {
        return [
            'equipment' => ['admin.equipment.edit', 'Admin/EquipmentEdit'],
            'categories' => ['admin.categories.edit', 'Admin/CategoryEdit'],
            'exercises' => ['admin.exercises.edit', 'Admin/ExerciseEdit'],
            'workout templates' => ['admin.workout-templates.edit', 'Admin/WorkoutTemplateEdit'],
            'programs' => ['admin.programs.edit', 'Admin/ProgramEdit'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('indexPages')]
    public function admin_can_render_index_page(string $route, string $component): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('indexPages')]
    public function non_admin_cannot_access_index_page(string $route): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route($route))->assertForbidden();
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('createPages')]
    public function admin_can_render_create_page(string $route, string $component): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('editPages')]
    public function admin_can_render_edit_page_with_id_prop(string $route, string $component): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route($route, ['id' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('id', 7)
            );
    }

    #[Test]
    public function non_admin_cannot_access_admin_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.categories.create'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.categories.edit', ['id' => 1]))->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_from_admin_pages(): void
    {
        $this->get(route('admin.categories.create'))->assertRedirect(route('login'));
    }
}

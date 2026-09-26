<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AboutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_visit_about_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('about'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('About')
                ->has('version')
            );
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('about'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function version_prop_reflects_app_version_config(): void
    {
        config(['app.version' => 'v1.2.3']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('about'))
            ->assertInertia(fn ($page) => $page
                ->where('version', 'v1.2.3')
            );
    }
}

<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function dashboard_page_is_rendered_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
        );
    }
}

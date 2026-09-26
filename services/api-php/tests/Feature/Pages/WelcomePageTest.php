<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function welcome_page_is_accessible(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
        );
    }

    #[Test]
    public function authenticated_user_is_redirected_from_welcome_page_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/');

        $response->assertRedirect(route('dashboard', absolute: false));
    }
}

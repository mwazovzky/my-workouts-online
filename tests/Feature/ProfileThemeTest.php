<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileThemeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_update_theme_preference_to_dark(): void
    {
        $user = User::factory()->create(['theme_preference' => 'system']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile/theme', [
                'theme_preference' => 'dark',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('dark', $user->refresh()->theme_preference);
    }

    #[Test]
    public function user_can_update_theme_preference_to_light(): void
    {
        $user = User::factory()->create(['theme_preference' => 'dark']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile/theme', [
                'theme_preference' => 'light',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('light', $user->refresh()->theme_preference);
    }

    #[Test]
    public function user_can_update_theme_preference_to_system(): void
    {
        $user = User::factory()->create(['theme_preference' => 'light']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile/theme', [
                'theme_preference' => 'system',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('system', $user->refresh()->theme_preference);
    }

    #[Test]
    public function theme_preference_update_rejects_invalid_value(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile/theme', [
                'theme_preference' => 'midnight',
            ]);

        $response->assertSessionHasErrors('theme_preference');
        $this->assertSame('system', $user->refresh()->theme_preference);
    }

    #[Test]
    public function theme_preference_update_requires_authentication(): void
    {
        $response = $this->patch('/profile/theme', [
            'theme_preference' => 'dark',
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function new_user_defaults_to_system_theme_preference(): void
    {
        $user = User::factory()->create();

        $this->assertSame('system', $user->theme_preference);
    }

    #[Test]
    public function inertia_shares_theme_preference_options(): void
    {
        $user = User::factory()->create(['theme_preference' => 'dark']);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->where('themePreference', 'dark')
                ->has('availableThemePreferences')
        );
    }
}

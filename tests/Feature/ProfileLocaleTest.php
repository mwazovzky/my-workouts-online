<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileLocaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_update_locale_to_russian(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile/locale', [
                'locale' => 'ru',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('ru', $user->refresh()->locale);
    }

    #[Test]
    public function user_can_update_locale_to_english(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile/locale', [
                'locale' => 'en',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('en', $user->refresh()->locale);
    }

    #[Test]
    public function locale_update_rejects_invalid_locale(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile/locale', [
                'locale' => 'fr',
            ]);

        $response->assertSessionHasErrors('locale');
        $this->assertSame('en', $user->refresh()->locale);
    }

    #[Test]
    public function locale_update_rejects_empty_locale(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile/locale', [
                'locale' => '',
            ]);

        $response->assertSessionHasErrors('locale');
    }

    #[Test]
    public function locale_update_requires_authentication(): void
    {
        $response = $this->patch('/profile/locale', [
            'locale' => 'ru',
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function new_user_defaults_to_english_locale(): void
    {
        $user = User::factory()->create();

        $this->assertSame('en', $user->locale);
    }

    #[Test]
    public function set_locale_middleware_sets_app_locale(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $this->assertSame('ru', app()->getLocale());
    }

    #[Test]
    public function inertia_shares_locale_and_translations(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->where('locale', 'ru')
                ->has('availableLocales')
                ->has('translations')
        );
    }
}

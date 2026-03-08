<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuestLocaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_update_locale_in_session(): void
    {
        $response = $this
            ->from('/login')
            ->patch('/locale', [
                'locale' => 'ru',
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasNoErrors();
        $this->assertSame('ru', session('locale'));
    }

    #[Test]
    public function guest_locale_update_rejects_invalid_locale(): void
    {
        $response = $this->patch('/locale', [
            'locale' => 'fr',
        ]);

        $response->assertSessionHasErrors('locale');
        $this->assertNull(session('locale'));
    }

    #[Test]
    public function guest_session_locale_sets_application_locale(): void
    {
        $this->withSession(['locale' => 'ru']);

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('ru', App::getLocale());
    }

    #[Test]
    public function guest_browser_preference_can_set_locale_when_session_is_missing(): void
    {
        $response = $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.8')->get('/login');

        $response->assertOk();
        $this->assertSame('ru', App::getLocale());
    }

    #[Test]
    public function inertia_shares_guest_locale_for_welcome_page(): void
    {
        $response = $this->withSession(['locale' => 'ru'])->get('/');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Welcome')
                ->where('locale', 'ru')
                ->has('availableLocales')
                ->has('translations')
        );
    }
}

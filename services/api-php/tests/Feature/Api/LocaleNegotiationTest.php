<?php

namespace Tests\Feature\Api;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleNegotiationTest extends TestCase
{
    use RefreshDatabase;

    private function programWithTranslations(): Program
    {
        return Program::createWithTranslations([
            'en' => ['name' => 'Strength', 'description' => 'Lift heavy'],
            'ru' => ['name' => 'Сила'],
        ]);
    }

    #[Test]
    public function accept_language_selects_translated_content_and_is_echoed(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $program = $this->programWithTranslations();

        $response = $this->actingAs($user)
            ->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.5')
            ->getJson("/api/v1/programs/{$program->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Сила')
            ->assertJsonPath('data.description', 'Lift heavy')
            ->assertHeader('Content-Language', 'ru');

        $this->assertStringContainsString('Accept-Language', $response->headers->get('Vary'));
    }

    #[Test]
    public function header_wins_over_the_stored_profile_locale(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $program = $this->programWithTranslations();

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'en')
            ->getJson("/api/v1/programs/{$program->id}")
            ->assertJsonPath('data.name', 'Strength')
            ->assertHeader('Content-Language', 'en');
    }

    #[Test]
    public function unsupported_or_missing_language_falls_back_to_english(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $program = $this->programWithTranslations();

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'fr-FR,de;q=0.8')
            ->getJson("/api/v1/programs/{$program->id}")
            ->assertJsonPath('data.name', 'Strength')
            ->assertHeader('Content-Language', 'en');

        $this->actingAs($user)
            ->getJson("/api/v1/programs/{$program->id}")
            ->assertJsonPath('data.name', 'Strength')
            ->assertHeader('Content-Language', 'en');
    }

    #[Test]
    public function validation_messages_follow_the_header(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'ru')
            ->patchJson('/api/v1/profile', ['name' => '', 'email' => $user->email])
            ->assertUnprocessable()
            ->assertFieldErrors(['name'], 'required');

        $message = $this->actingAs($user)
            ->withHeader('Accept-Language', 'ru')
            ->patchJson('/api/v1/profile', ['name' => '', 'email' => $user->email])
            ->fieldError('name')['detail'];

        $this->assertStringContainsString('обязательно', $message);
    }
}

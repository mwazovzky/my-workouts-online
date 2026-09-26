<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetaApiTest extends TestCase
{
    #[Test]
    public function exposes_locales_and_theme_options_publicly(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertExactJson(['data' => [
                'available_locales' => ['en' => 'English', 'ru' => 'Русский'],
                'theme_preferences' => ['light', 'dark', 'system'],
            ]]);
    }
}

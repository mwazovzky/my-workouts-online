<?php

namespace Tests\Feature\Pages;

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
}

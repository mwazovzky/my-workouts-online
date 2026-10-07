<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    #[Test]
    public function it_returns_ok_for_health_check(): void
    {
        $response = $this->getJson('/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertSame(['status', 'timestamp'], array_keys($response->json()));
    }

    #[Test]
    public function it_returns_ok_for_readiness_check_when_every_component_is_up(): void
    {
        $response = $this->getJson('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('components.database', 'up')
            ->assertJsonPath('components.jwt', 'up');

        $this->assertSame(['status', 'components', 'timestamp'], array_keys($response->json()));
        $this->assertSame(['database', 'jwt'], array_keys($response->json('components')));
    }
}

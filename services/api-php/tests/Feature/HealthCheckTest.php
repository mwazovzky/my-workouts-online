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
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'timestamp'])
            ->assertJson(['status' => 'ok']);
    }

    #[Test]
    public function it_returns_ok_for_readiness_check_when_database_is_up(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJson(['status' => 'ok'])
            ->assertJsonStructure([
                'status',
                'timestamp',
                'components' => ['database'],
            ]);
    }
}

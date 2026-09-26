<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * A missing key pair must degrade only the token operations, never an
 * anonymous request or the liveness probe.
 */
class MissingJwtKeysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.private_key' => null, 'jwt.public_key' => null]);
    }

    #[Test]
    public function liveness_and_public_routes_still_work(): void
    {
        $this->getJson('/health')->assertOk();
        $this->getJson('/api/v1/meta')->assertOk();
        $this->getJson('/api/v1/programs')->assertUnauthorized();
    }

    #[Test]
    public function readiness_reports_the_missing_keys(): void
    {
        $this->getJson('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('components.jwt', 'down')
            ->assertJsonPath('components.database', 'up');
    }

    #[Test]
    public function token_operations_fail_with_a_clear_message(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/JWT_PRIVATE_KEY/');

        app(TokenServiceInterface::class)->issuePair($user);
    }

    #[Test]
    public function readiness_is_ok_when_keys_are_present(): void
    {
        config(['jwt.private_key' => env('JWT_PRIVATE_KEY'), 'jwt.public_key' => env('JWT_PUBLIC_KEY')]);
        $this->app->forgetInstance(TokenServiceInterface::class);

        $this->getJson('/health/ready')->assertOk()->assertJsonPath('components.jwt', 'up');
    }
}

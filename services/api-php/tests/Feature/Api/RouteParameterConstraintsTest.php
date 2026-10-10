<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Route model binding must only run for numeric ids. Without a route
 * constraint, a string such as "8abc" reaches the database, where MySQL
 * coerces it to 8 and resolves someone else's record. The requests below
 * are unauthenticated on purpose: with the constraint no route matches and
 * the API answers 404 before authentication, whereas an unconstrained route
 * would answer 401.
 */
class RouteParameterConstraintsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function nonNumericIdRoutes(): array
    {
        return [
            'program show' => ['GET', '/api/v1/programs/8abc'],
            'program enrollment' => ['PUT', '/api/v1/programs/8abc/enrollment'],
            'workout template show' => ['GET', '/api/v1/workout-templates/8abc'],
            'workout show' => ['GET', '/api/v1/workouts/8abc'],
            'workout delete' => ['DELETE', '/api/v1/workouts/8abc'],
            'workout activities' => ['PUT', '/api/v1/workouts/8abc/activities'],
            'workout complete' => ['POST', '/api/v1/workouts/8abc/complete'],
            'workout repeat' => ['POST', '/api/v1/workouts/8abc/repeat'],
            'admin equipment' => ['DELETE', '/api/v1/admin/equipment/8abc'],
            'admin category' => ['DELETE', '/api/v1/admin/categories/8abc'],
            'admin exercise' => ['DELETE', '/api/v1/admin/exercises/8abc'],
            'admin workout template' => ['DELETE', '/api/v1/admin/workout-templates/8abc'],
            'admin program' => ['DELETE', '/api/v1/admin/programs/8abc'],
        ];
    }

    #[Test]
    #[DataProvider('nonNumericIdRoutes')]
    public function a_non_numeric_id_matches_no_route(string $method, string $uri): void
    {
        $this->json($method, $uri)
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    #[Test]
    public function a_numeric_id_still_reaches_the_route(): void
    {
        $this->getJson('/api/v1/workouts/8')->assertUnauthorized();
    }
}

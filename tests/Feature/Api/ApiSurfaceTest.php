<?php

namespace Tests\Feature\Api;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

/**
 * Coverage of the JSON API surface (routes/api.php, the `api` middleware group).
 * Guarantees every API endpoint is authentication-guarded and that the canonical
 * `/api/user` endpoint returns the authenticated user.
 */
class ApiSurfaceTest extends EventModuleTestCase
{
    public function test_api_user_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_api_user_returns_authenticated_user(): void
    {
        $user = $this->createUser(['email' => 'api-user@example.com']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonFragment(['email' => 'api-user@example.com']);
    }

    public function test_every_api_route_is_authentication_guarded(): void
    {
        $unguarded = [];

        foreach ($this->apiRoutes() as $route) {
            $guarded = collect($route->gatherMiddleware())
                ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth'));

            if (! $guarded) {
                $unguarded[] = $route->uri();
            }
        }

        $this->assertSame(
            [],
            $unguarded,
            "API endpoints missing authentication middleware:\n".implode("\n", $unguarded)
        );
    }

    /** @return list<RoutingRoute> */
    private function apiRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if (in_array('api', $route->gatherMiddleware(), true) && str_starts_with($route->uri(), 'api/')) {
                $routes[] = $route;
            }
        }

        return $routes;
    }
}

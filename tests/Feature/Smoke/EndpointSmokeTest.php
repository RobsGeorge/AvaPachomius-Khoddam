<?php

namespace Tests\Feature\Smoke;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

/**
 * Behavioural smoke coverage of every parameterless GET endpoint (the bulk of the
 * server-rendered UI). Each page is requested and must not return a 5xx — this
 * exercises the controller, its dependencies, and the Blade view end to end.
 *
 * Routes that require URL parameters (e.g. /courses/{course}) are covered by their
 * own module feature tests; here we guarantee the whole no-parameter UI surface is
 * reachable without server errors, as a superadmin (who bypasses permission gates)
 * and as a guest (who must be redirected, never error).
 */
class EndpointSmokeTest extends EventModuleTestCase
{
    /**
     * Endpoints intentionally excluded from the "render as superadmin" sweep, with
     * the reason. Keep this list short and justified.
     *
     * @var array<string, string>
     */
    private array $skipAsSuperadmin = [
        'logout' => 'terminates the session under test',
        'superadmin.system-tests.run' => 'shells out to the test runner (POST only anyway)',
    ];

    public function test_parameterless_get_pages_do_not_error_for_superadmin(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);
        $this->actingAs($admin);

        $failures = [];

        foreach ($this->parameterlessWebGetRoutes() as $route) {
            $name = $route->getName() ?? $route->uri();

            if (isset($this->skipAsSuperadmin[$name])) {
                continue;
            }

            $response = $this->get('/'.ltrim($route->uri(), '/'));
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $why = $response->exception
                    ? ' :: '.get_class($response->exception).': '.$response->exception->getMessage()
                    : '';
                $failures[] = sprintf('%s (%s) → %d%s', $name, $route->uri(), $status, $why);
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Parameterless GET endpoints returned a server error for a superadmin:\n"
                .implode("\n", $failures)
        );
    }

    public function test_authenticated_pages_redirect_guests_without_erroring(): void
    {
        $failures = [];

        foreach ($this->parameterlessWebGetRoutes() as $route) {
            if (! $this->requiresAuth($route)) {
                continue;
            }

            $response = $this->get('/'.ltrim($route->uri(), '/'));
            $status = $response->getStatusCode();

            // Guests must be bounced (redirect) or refused — never a server error,
            // and never served the page (which would be an auth hole).
            if ($status >= 500 || $status === 200) {
                $failures[] = sprintf('%s (%s) → %d', $route->getName() ?? $route->uri(), $route->uri(), $status);
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Auth-protected GET endpoints did not safely reject a guest:\n".implode("\n", $failures)
        );
    }

    /** @return list<RoutingRoute> */
    private function parameterlessWebGetRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (str_contains($route->uri(), '{')) {
                continue;
            }
            if (! in_array('web', $route->gatherMiddleware(), true)) {
                continue; // skip api/sanctum surface (covered by the Api suite)
            }

            $routes[] = $route;
        }

        return $routes;
    }

    private function requiresAuth(RoutingRoute $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            // Middleware are returned as aliases (e.g. "auth", "auth:sanctum").
            if (is_string($middleware) && str_starts_with($middleware, 'auth')) {
                return true;
            }
        }

        return false;
    }
}

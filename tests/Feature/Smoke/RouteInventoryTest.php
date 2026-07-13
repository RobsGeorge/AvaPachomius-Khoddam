<?php

namespace Tests\Feature\Smoke;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

/**
 * Structural coverage of EVERY endpoint: asserts each registered route maps to a
 * controller action that actually exists. This catches dangling routes (renamed or
 * deleted controller methods) without needing any fixtures, and automatically covers
 * new routes as they are added.
 */
class RouteInventoryTest extends EventModuleTestCase
{
    public function test_every_route_resolves_to_an_existing_controller_action(): void
    {
        $broken = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $action = $route->getActionName();

            // Closure / redirect routes have no controller class to verify.
            if ($action === 'Closure' || ! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action, 2);

            if (! class_exists($class)) {
                $broken[] = "{$route->uri()} → missing class {$class}";
                continue;
            }

            if (! method_exists($class, $method) && ! method_exists($class, '__call')) {
                $broken[] = "{$route->uri()} → {$class} has no method {$method}()";
            }
        }

        $this->assertSame(
            [],
            $broken,
            "Routes pointing at non-existent controller actions:\n".implode("\n", $broken)
        );
    }

    public function test_every_route_has_a_unique_registered_uri_method_pair(): void
    {
        $seen = [];
        $dupes = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $verb) {
                $key = $verb.' '.$route->uri();
                if (isset($seen[$key])) {
                    $dupes[] = $key;
                }
                $seen[$key] = true;
            }
        }

        $this->assertSame([], $dupes, "Duplicate route definitions:\n".implode("\n", $dupes));
    }
}

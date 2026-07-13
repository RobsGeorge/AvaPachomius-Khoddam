<?php

namespace Tests\Feature\UseCases;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

/**
 * Persona × route authorization boundary (see docs/product/test-cases/test-case-catalog.md,
 * TC-AUTHZ-*). Data-driven: enumerates the real routes and asserts that an under-permissioned
 * actor is refused the admin/superadmin/staff surfaces, that guests are bounced, and that a
 * SuperAdmin can reach them. This one class covers the "every path × role denial" dimension
 * cheaply, without hardcoding role names (CLAUDE.md rule 4).
 */
class AuthorizationMatrixTest extends EventModuleTestCase
{
    public function test_plain_student_is_denied_privileged_pages(): void
    {
        // Approved, verified, no admin/superadmin, no course/service roles.
        $student = $this->createUser(['email' => 'authz-student@example.com']);
        $this->actingAs($student);

        $privileged = $this->privilegedGetRoutes();

        // Guard against a vacuous pass if route middleware ever changes shape.
        $this->assertGreaterThan(
            15,
            count($privileged),
            'Expected many privileged routes to assert against; found '.count($privileged)
        );

        $holes = [];
        foreach ($privileged as $route) {
            $status = $this->get('/'.ltrim($route->uri(), '/'))->getStatusCode();
            // Refused = 403 or a redirect. Being served the page (200) is an authorization hole.
            if ($status === 200) {
                $holes[] = sprintf('%s (%s) served 200 to a plain student', $route->getName() ?? $route->uri(), $route->uri());
            }
        }

        $this->assertSame([], $holes, "Privileged pages leaked to an under-permissioned user:\n".implode("\n", $holes));
    }

    public function test_guest_cannot_reach_privileged_pages(): void
    {
        $holes = [];
        foreach ($this->privilegedGetRoutes() as $route) {
            $status = $this->get('/'.ltrim($route->uri(), '/'))->getStatusCode();
            if ($status === 200) {
                $holes[] = sprintf('%s (%s) served 200 to a guest', $route->getName() ?? $route->uri(), $route->uri());
            }
        }

        $this->assertSame([], $holes, "Privileged pages leaked to a guest:\n".implode("\n", $holes));
    }

    public function test_superadmin_can_reach_privileged_pages(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'authz-super@example.com']);
        $this->actingAs($admin);

        $errors = [];
        foreach ($this->privilegedGetRoutes() as $route) {
            $status = $this->get('/'.ltrim($route->uri(), '/'))->getStatusCode();
            // SuperAdmin bypasses permission checks; a privileged page must not 403/500 for them.
            if ($status === 403 || $status >= 500) {
                $errors[] = sprintf('%s (%s) -> %d for superadmin', $route->getName() ?? $route->uri(), $route->uri(), $status);
            }
        }

        $this->assertSame([], $errors, "Privileged pages refused/errored for a superadmin:\n".implode("\n", $errors));
    }

    /**
     * Parameterless web GET routes gated by an admin / superadmin / staff / events-admin
     * middleware — i.e. pages a plain student must never be served.
     *
     * @return list<RoutingRoute>
     */
    private function privilegedGetRoutes(): array
    {
        $gates = ['admin', 'superadmin', 'permission:staff', 'attendance.staff', 'events.admin'];
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (str_contains($route->uri(), '{')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            if (! in_array('web', $middleware, true)) {
                continue;
            }
            foreach ($gates as $gate) {
                if (in_array($gate, $middleware, true)) {
                    $routes[] = $route;
                    break;
                }
            }
        }

        return $routes;
    }
}

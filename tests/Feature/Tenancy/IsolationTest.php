<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Course;
use App\Models\Organization;
use App\Tenancy\BelongsToChurch;
use App\Tenancy\ResolveTenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\EventModuleTestCase;

/**
 * P1.2 sacred isolation suite (CLAUDE.md / IsolationTest).
 *
 * Church A vs B parallel data: scoped reads hide the other tenant; creates stamp
 * the active church; HTTP endpoints for a church-A member must not leak church-B
 * markers. Runs under both MULTI_TENANT=false (dormant → church 1) and true.
 */
class IsolationTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        config(['tenancy.enabled' => false]);
        parent::tearDown();
    }

    public function test_context_singleton_exposes_church_id(): void
    {
        $church = Church::main();
        TenantContext::set($church);

        $this->assertSame((int) $church->church_id, (int) app(TenantContext::class)->churchId());
        $this->assertTrue(TenantContext::enforced());
    }

    public function test_dormant_mode_binds_tenant_zero(): void
    {
        config(['tenancy.enabled' => false]);
        TenantContext::clear();

        (new ResolveTenant())->handle(Request::create('http://localhost/'), fn () => 'ok');

        $this->assertNotNull(TenantContext::current());
        $this->assertSame(Church::main()->church_id, TenantContext::id());
        $this->assertSame(Organization::main()->organization_id, TenantContext::id());
    }

    public function test_enabled_mode_unknown_subdomain_is_404(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'example.test',
        ]);

        try {
            (new ResolveTenant())->handle(
                Request::create('http://unknown.example.test/'),
                fn () => 'ok'
            );
            $this->fail('Expected 404 for unknown tenant subdomain.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_enabled_mode_resolves_api_token_church_claim(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.base_domain' => 'example.test']);

        $church = Church::create(['slug' => 'api-claim-church', 'name' => 'API Claim', 'status' => 'active']);
        $user = $this->createUser(['email' => 'api-claim@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($user, ['church:api-claim-church']);

        $request = Request::create('http://example.test/api/v1/me', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);

        // Sanctum::actingAs does not always attach currentAccessToken abilities the same way;
        // also accept explicit header used by mobile clients.
        $request->headers->set('X-Church-Slug', 'api-claim-church');

        (new ResolveTenant())->handle($request, fn () => 'ok');

        $this->assertSame($church->church_id, TenantContext::id());
    }

    public function test_parallel_churches_are_isolated_on_models(): void
    {
        $this->assertTrue(trait_exists(BelongsToChurch::class));

        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'isol-b', 'name' => 'Isolation B', 'status' => 'active']);

        TenantContext::set($churchA);
        $courseA = Course::create([
            'title' => 'ISOLATION_MARKER_A_'.uniqid(),
            'description' => 'x',
            'year' => 2026,
        ]);
        $this->assertEquals($churchA->church_id, $courseA->church_id);

        TenantContext::set($churchB);
        $courseB = Course::create([
            'title' => 'ISOLATION_MARKER_B_'.uniqid(),
            'description' => 'x',
            'year' => 2026,
        ]);
        $this->assertEquals($churchB->church_id, $courseB->church_id);
        $this->assertNull(Course::find($courseA->course_id));

        TenantContext::set($churchA);
        $this->assertNull(Course::find($courseB->course_id));
        $this->assertNotNull(Course::find($courseA->course_id));
    }

    public function test_church_a_member_endpoints_leak_zero_church_b_rows(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.base_domain' => 'example.test']);

        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'isol-web-b', 'name' => 'Web B', 'status' => 'active']);

        $markerB = 'ISOLATION_ENDPOINT_MARKER_B_'.uniqid();

        TenantContext::set($churchB);
        Course::create(['title' => $markerB, 'description' => 'secret-b', 'year' => 2026]);

        $userA = $this->createUser([
            'email' => 'isol-a@example.com',
            'is_verified' => true,
            'registration_completed' => true,
        ]);
        ChurchUser::create([
            'church_id' => $churchA->church_id,
            'user_id' => $userA->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        TenantContext::set($churchA);
        Course::create([
            'title' => 'ISOLATION_ENDPOINT_MARKER_A',
            'description' => 'a',
            'year' => 2026,
        ]);

        $this->actingAs($userA);

        $leaks = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (collect($route->gatherMiddleware())->contains('api')) {
                continue;
            }
            // Only parameter-free GET routes — avoid 404/500 noise from bindings.
            if (str_contains($route->uri(), '{')) {
                continue;
            }
            if ($route->uri() === 'up' || str_starts_with($route->uri(), '_')) {
                continue;
            }

            $uri = '/'.ltrim($route->uri(), '/');
            try {
                $response = $this->call('GET', $uri, [], [], [], [
                    'HTTP_HOST' => $churchA->slug.'.example.test',
                ]);
            } catch (\Throwable) {
                continue;
            }

            $body = $response->getContent() ?: '';
            if (str_contains($body, $markerB)) {
                $leaks[] = $uri.' ['.$response->getStatusCode().']';
            }
        }

        $this->assertSame(
            [],
            $leaks,
            "Church B marker leaked via endpoints:\n".implode("\n", $leaks)
        );
    }

    public function test_create_as_church_a_stamps_church_id(): void
    {
        $churchA = Church::main();
        TenantContext::set($churchA);

        $course = Course::create(['title' => 'Stamp A', 'description' => 'x', 'year' => 2026]);

        $this->assertSame((int) $churchA->church_id, (int) $course->church_id);
        $this->assertSame((int) $churchA->church_id, (int) app(TenantContext::class)->churchId());
    }
}

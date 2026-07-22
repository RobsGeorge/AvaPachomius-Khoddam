<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Tenancy\EnsureChurchMember;
use App\Tenancy\ResolveTenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\EventModuleTestCase;

/**
 * T1 — request→church resolution and the membership gate. Both are dormant while
 * tenancy is disabled (production until T7 cutover) and active once a church is bound.
 */
class ChurchMiddlewareTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_resolve_tenant_binds_main_when_enabled(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'staging.example.test',
            'app.url' => 'https://staging.example.test',
        ]);

        $response = (new ResolveTenant())->handle(Request::create('http://localhost/'), fn () => 'ok');

        $this->assertSame('ok', $response);
        $this->assertNotNull(TenantContext::current());
        $this->assertSame(Church::main()->church_id, TenantContext::id());
    }

    public function test_resolve_tenant_is_dormant_when_disabled(): void
    {
        config(['tenancy.enabled' => false]);

        (new ResolveTenant())->handle(Request::create('http://localhost/'), fn () => 'ok');

        // P1.2: dormant mode still binds Tenant Zero so scopes/stamps are consistent.
        $this->assertNotNull(TenantContext::current());
        $this->assertSame(Church::main()->church_id, TenantContext::id());
        $this->assertTrue(TenantContext::enforced());
    }

    public function test_member_passes_the_gate(): void
    {
        config(['tenancy.enabled' => true]);
        $church = Church::main();
        $member = $this->createUser(['email' => 'gate-member@example.com']);
        ChurchUser::create(['church_id' => $church->church_id, 'user_id' => $member->user_id, 'status' => 'active']);
        TenantContext::set($church);

        $response = (new EnsureChurchMember())->handle($this->requestAs($member), fn () => 'ok');

        $this->assertSame('ok', $response);
    }

    public function test_non_member_is_blocked(): void
    {
        config(['tenancy.enabled' => true]);
        $church = Church::main();
        $stranger = $this->createUser(['email' => 'gate-stranger@example.com']);
        TenantContext::set($church);

        try {
            (new EnsureChurchMember())->handle($this->requestAs($stranger), fn () => 'ok');
            $this->fail('Expected a 403 for a non-member.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_superadmin_and_unbound_context_bypass_the_gate(): void
    {
        config(['tenancy.enabled' => true]);
        $church = Church::main();
        $superadmin = $this->createUser(['is_superadmin' => true, 'email' => 'gate-super@example.com']);
        $stranger = $this->createUser(['email' => 'gate-stranger2@example.com']);

        // Superadmin passes even without membership.
        TenantContext::set($church);
        $this->assertSame('ok', (new EnsureChurchMember())->handle($this->requestAs($superadmin), fn () => 'ok'));

        // MULTI_TENANT=false → membership gate skipped even if a church is bound.
        config(['tenancy.enabled' => false]);
        TenantContext::set($church);
        $this->assertSame('ok', (new EnsureChurchMember())->handle($this->requestAs($stranger), fn () => 'ok'));
    }

    public function test_church_switcher_query_excludes_inactive_memberships(): void
    {
        $main = Church::main();
        $other = Church::create(['slug' => 'inactive-member-church', 'name' => 'Inactive Member', 'status' => 'active']);
        $user = $this->createUser(['email' => 'switcher-member@example.com']);

        ChurchUser::create([
            'church_id' => $main->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        ChurchUser::create([
            'church_id' => $other->church_id,
            'user_id' => $user->user_id,
            'status' => 'suspended',
            'joined_at' => now(),
        ]);

        // Mirrors AppLayoutComposer non-superadmin selectableChurches query.
        $selectable = $user->churches()
            ->where('church.status', 'active')
            ->wherePivot('status', 'active')
            ->orderBy('church.name')
            ->get();

        $this->assertTrue($selectable->contains('church_id', $main->church_id));
        $this->assertFalse($selectable->contains('church_id', $other->church_id));
    }

    private function requestAs(\App\Models\User $user): Request
    {
        $request = Request::create('http://localhost/');
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}

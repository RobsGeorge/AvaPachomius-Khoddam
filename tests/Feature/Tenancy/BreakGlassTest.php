<?php

namespace Tests\Feature\Tenancy;

use App\Models\AccessLedgerEntry;
use App\Models\BreakGlassGrant;
use App\Models\Church;
use App\Models\Organization;
use App\Services\BreakGlass\BreakGlassService;
use App\Services\PlatformAccessService;
use App\Tenancy\TenantDatabaseResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\EventModuleTestCase;

class BreakGlassTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        try {
            $request = request();
            if ($request->hasSession() && PlatformAccessService::isActive()) {
                PlatformAccessService::stop($request);
            }
        } catch (\Throwable) {
            // No session in unit-style calls — ignore.
        }
        parent::tearDown();
    }

    public function test_staff_without_grant_is_denied(): void
    {
        $church = Church::main();
        $staff = $this->createUser(['is_superadmin' => true, 'email' => 'bg-deny@example.com']);

        $this->expectException(HttpException::class);
        app(BreakGlassService::class)->assertAllowed($staff, $church);
    }

    public function test_unexpired_grant_allows_and_logs_break_glass_open(): void
    {
        $church = Church::main();
        $org = TenantDatabaseResolver::resolvePlacementOrganization($church);
        $this->assertNotNull($org);

        $staff = $this->createUser(['is_superadmin' => true, 'email' => 'bg-allow@example.com']);
        $service = app(BreakGlassService::class);

        $grant = $service->grant($staff, $staff, $org, 'Incident review for افتقاد follow-up', 60);
        $this->assertTrue($grant->self_approved);

        $used = $service->assertAllowed($staff, $church);
        $this->assertSame((int) $grant->break_glass_grant_id, (int) $used->break_glass_grant_id);

        $row = AccessLedgerEntry::query()
            ->where('action', AccessLedgerEntry::ACTION_BREAK_GLASS_OPEN)
            ->where('subject_id', $grant->break_glass_grant_id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(AccessLedgerEntry::ACTOR_STAFF, $row->actor_type);
        $this->assertSame((int) $staff->user_id, (int) $row->actor_id);
        $this->assertTrue((bool) ($row->context['self_approved'] ?? false));
    }

    public function test_expired_grant_denies_and_does_not_extend(): void
    {
        $church = Church::main();
        $org = TenantDatabaseResolver::resolvePlacementOrganization($church);
        $staff = $this->createUser(['is_superadmin' => true, 'email' => 'bg-expired@example.com']);

        $grant = BreakGlassGrant::query()->create([
            'staff_id' => $staff->user_id,
            'organization_id' => $org->organization_id,
            'reason' => 'Expired test grant',
            'granted_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'self_approved' => true,
            'created_at' => now()->subHours(2),
        ]);

        $expiresBefore = $grant->expires_at->copy();

        try {
            app(BreakGlassService::class)->assertAllowed($staff, $church);
            $this->fail('Expected denial for expired grant');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $grant->refresh();
        $this->assertTrue($grant->expires_at->equalTo($expiresBefore));
        $this->assertSame(
            0,
            AccessLedgerEntry::query()->where('action', AccessLedgerEntry::ACTION_BREAK_GLASS_OPEN)->count()
        );
    }

    public function test_self_approved_grant_logs_identically(): void
    {
        $church = Church::main();
        $org = TenantDatabaseResolver::resolvePlacementOrganization($church);
        $solo = $this->createUser(['is_superadmin' => true, 'email' => 'bg-solo@example.com']);
        $other = $this->createUser(['is_superadmin' => true, 'email' => 'bg-other@example.com']);
        $service = app(BreakGlassService::class);

        $selfGrant = $service->grant($solo, $solo, $org, 'Solo builder self grant', 30);
        $this->assertTrue($selfGrant->self_approved);
        $service->assertAllowed($solo, $church);

        $selfRow = AccessLedgerEntry::query()
            ->where('action', AccessLedgerEntry::ACTION_BREAK_GLASS_OPEN)
            ->where('subject_id', $selfGrant->break_glass_grant_id)
            ->first();

        // Second staff granted by first — not self-approved; log shape must match.
        $peerGrant = $service->grant($solo, $other, $org, 'Peer grant for support', 30);
        $this->assertFalse($peerGrant->self_approved);
        $service->assertAllowed($other, $church);

        $peerRow = AccessLedgerEntry::query()
            ->where('action', AccessLedgerEntry::ACTION_BREAK_GLASS_OPEN)
            ->where('subject_id', $peerGrant->break_glass_grant_id)
            ->first();

        $this->assertNotNull($selfRow);
        $this->assertNotNull($peerRow);
        $this->assertSame($selfRow->actor_type, $peerRow->actor_type);
        $this->assertSame($selfRow->action, $peerRow->action);
        $this->assertSame($selfRow->subject_type, $peerRow->subject_type);
        $this->assertArrayHasKey('grant_id', $selfRow->context);
        $this->assertArrayHasKey('grant_id', $peerRow->context);
        $this->assertArrayHasKey('self_approved', $selfRow->context);
        $this->assertArrayHasKey('self_approved', $peerRow->context);
        $this->assertTrue((bool) $selfRow->context['self_approved']);
        $this->assertFalse((bool) $peerRow->context['self_approved']);
    }

    public function test_grant_check_fails_closed_on_exception(): void
    {
        $church = Church::main();
        $staff = $this->createUser(['is_superadmin' => true, 'email' => 'bg-failclosed@example.com']);

        // Force lookup failure by pointing at a non-persisted organization id.
        $bogus = new Organization([
            'organization_id' => 999999,
            'subdomain' => 'bogus',
            'name' => 'Bogus',
            'type' => Organization::TYPE_CHURCH,
            'status' => 'active',
        ]);
        $bogus->organization_id = 999999;

        $service = app(BreakGlassService::class);
        $this->assertNull($service->activeGrant($staff, $bogus));
    }

    public function test_platform_access_requires_grant(): void
    {
        $church = Church::main();
        $staff = $this->createUser(['is_superadmin' => true, 'email' => 'bg-platform@example.com']);
        $this->actingAs($staff);
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        try {
            PlatformAccessService::start($church, $staff, $request);
            $this->fail('Expected platform access without grant to abort');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertFalse(PlatformAccessService::isActive());

        $org = TenantDatabaseResolver::resolvePlacementOrganization($church);
        app(BreakGlassService::class)->grant($staff, $staff, $org, 'Need platform access for support', 60);

        PlatformAccessService::start($church, $staff, $request);
        $this->assertTrue(PlatformAccessService::isActive());
        $this->assertSame(
            1,
            AccessLedgerEntry::query()->where('action', AccessLedgerEntry::ACTION_BREAK_GLASS_OPEN)->count()
        );
    }

    public function test_revoking_grant_ends_an_already_active_platform_access_session(): void
    {
        $church = Church::main();
        $staff = $this->createUser(['is_superadmin' => true, 'email' => 'bg-revoke-live@example.com']);
        $this->actingAs($staff);
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        $org = TenantDatabaseResolver::resolvePlacementOrganization($church);
        $grant = app(BreakGlassService::class)->grant($staff, $staff, $org, 'Support session', 60);

        PlatformAccessService::start($church, $staff, $request);
        $this->assertTrue(PlatformAccessService::isActive());

        app(BreakGlassService::class)->revoke($staff, $grant);

        // The session flag alone would still say "active" — isActive() must re-check
        // the grant itself, not just the session, and tear the stale session down.
        $this->assertFalse(PlatformAccessService::isActive());
        $this->assertFalse(session()->has(PlatformAccessService::SESSION_KEY));

        $this->assertNotNull(
            \App\Models\ActivityLog::query()
                ->where('route_name', 'platform_church_access_expired')
                ->latest('activity_log_id')
                ->first()
        );
    }

    public function test_expired_grant_ends_an_already_active_platform_access_session(): void
    {
        $church = Church::main();
        $staff = $this->createUser(['is_superadmin' => true, 'email' => 'bg-expire-live@example.com']);
        $this->actingAs($staff);
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        $org = TenantDatabaseResolver::resolvePlacementOrganization($church);
        app(BreakGlassService::class)->grant($staff, $staff, $org, 'Short support session', 5);

        PlatformAccessService::start($church, $staff, $request);
        $this->assertTrue(PlatformAccessService::isActive());

        $this->travel(6)->minutes();

        $this->assertFalse(PlatformAccessService::isActive());
        $this->assertFalse(session()->has(PlatformAccessService::SESSION_KEY));
    }

    public function test_grant_ui_creates_self_approved_grant(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.console_host' => 'admin.test']);

        $church = Church::main();
        $staff = $this->createUser(['is_superadmin' => true, 'email' => 'bg-ui@example.com']);

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($staff)
            ->post(route('superadmin.churches.break-glass.store', $church), [
                'reason' => 'UI grant for pastoral support',
                'duration_minutes' => 60,
            ])
            ->assertRedirect();

        $grant = BreakGlassGrant::query()->where('staff_id', $staff->user_id)->first();
        $this->assertNotNull($grant);
        $this->assertTrue($grant->self_approved);
        $this->assertTrue($grant->isActive());
    }
}

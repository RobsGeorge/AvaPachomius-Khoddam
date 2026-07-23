<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\ChurchUser;
use App\Tenancy\TenantContext;
use Tests\Support\EventModuleTestCase;

/**
 * Gap 3 — the API mirrors the web's per-church capability gating. A church that has a
 * feature disabled must 404 the matching /api/v1 route (the feature does not exist there),
 * so a mobile client discovers the same feature surface as the web. Dormant while tenancy
 * is disabled (no church bound → every feature available), so production is unaffected.
 */
class ApiCapabilityGateTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function tokenHeadersFor(Church $church): array
    {
        // Make the actor a member so the membership gate passes; this isolates the
        // behavior under test to the capability gate itself.
        $user = $this->createUser(['email' => 'cap-'.uniqid().'@example.com']);
        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return ['Authorization' => 'Bearer '.$user->createToken('phpunit')->plainTextToken];
    }

    public function test_a_disabled_capability_404s_its_api_route(): void
    {
        config(['tenancy.enabled' => true]);

        // A fresh church has no catalog capabilities enabled.
        $church = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);

        $this->withHeaders($this->tokenHeadersFor($church))
            ->getJson("http://{$church->slug}.localhost/api/v1/announcements")
            ->assertStatus(404);
    }

    public function test_an_enabled_capability_allows_its_api_route(): void
    {
        config(['tenancy.enabled' => true]);

        $church = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);
        ChurchCapability::create([
            'church_id' => $church->church_id,
            'capability_key' => 'announcements',
            'enabled' => true,
        ]);

        $this->withHeaders($this->tokenHeadersFor($church))
            ->getJson("http://{$church->slug}.localhost/api/v1/announcements")
            ->assertOk();
    }

    public function test_capability_gating_is_dormant_when_tenancy_is_disabled(): void
    {
        // Production posture (MULTI_TENANT=false): no church is bound, so the gate is a
        // no-op and the feature is reachable exactly as before.
        config(['tenancy.enabled' => false]);

        $user = $this->createUser(['email' => 'cap-dormant@example.com']);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('phpunit')->plainTextToken)
            ->getJson('/api/v1/announcements')
            ->assertOk();
    }
}

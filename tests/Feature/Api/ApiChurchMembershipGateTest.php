<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\ChurchUser;
use Tests\Support\EventModuleTestCase;

/**
 * The church-membership gate must enforce on the API (bearer-token) path, not only web.
 * It runs inside the auth:sanctum group so the token user is resolved before the check;
 * otherwise a valid token could read another church's church-wide data by hitting that
 * church's host. Only active while tenancy is enabled (production until the T7 cutover).
 */
class ApiChurchMembershipGateTest extends EventModuleTestCase
{
    public function test_token_from_a_non_member_church_is_rejected_on_the_api(): void
    {
        config(['tenancy.enabled' => true]);
        Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);
        $stranger = $this->createUser(['email' => 'api-stranger@example.com']); // member of no church
        $token = $stranger->createToken('mobile')->plainTextToken;

        // Host resolves to St Mark; the stranger is not a member → 403.
        $this->withToken($token)
            ->getJson('http://stmark.localhost/api/v1/me')
            ->assertStatus(403);
    }

    public function test_member_of_the_resolved_church_passes_the_api_gate(): void
    {
        config(['tenancy.enabled' => true]);
        $church = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);
        $member = $this->createUser(['email' => 'api-member@example.com']);
        ChurchUser::create(['church_id' => $church->church_id, 'user_id' => $member->user_id, 'status' => 'active']);
        $token = $member->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('http://stmark.localhost/api/v1/me')
            ->assertOk();
    }
}

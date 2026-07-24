<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\ChurchUser;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\EventModuleTestCase;

/**
 * An API token carries a `church:{slug}` ability naming the church it was issued for.
 * ResolveTenant reads that claim to pin every request from the token to its church
 * (regardless of host), and EnsureTokenChurch rejects any attempt to override the tenant
 * (X-Church-Slug header / custom domain) to a church the token is not scoped to — even for
 * a user who is a member of both churches, which is why the membership gate alone is not
 * enough. Dormant while tenancy is disabled.
 */
class ApiTokenChurchScopeTest extends EventModuleTestCase
{
    public function test_login_stamps_the_resolved_church_on_the_token(): void
    {
        config(['tenancy.enabled' => true]);
        $church = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);
        $user = $this->createUser(['email' => 'login-scope@example.com']); // factory password: 'password'
        ChurchUser::create(['church_id' => $church->church_id, 'user_id' => $user->user_id, 'status' => 'active']);

        $login = $this->postJson("http://{$church->slug}.localhost/api/v1/login", [
            'email' => 'login-scope@example.com',
            'password' => 'password',
        ])->assertOk();

        $token = PersonalAccessToken::findToken($login->json('token'));
        $this->assertSame(["church:{$church->slug}"], $token->abilities);
    }

    public function test_a_scoped_token_is_pinned_to_its_church_regardless_of_host(): void
    {
        config(['tenancy.enabled' => true]);
        $churchA = Church::create(['slug' => 'stluke', 'name' => 'St Luke', 'status' => 'active']);
        $churchB = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);

        // Member of A only, with a token scoped to A.
        $user = $this->createUser(['email' => 'pinned@example.com']);
        ChurchUser::create(['church_id' => $churchA->church_id, 'user_id' => $user->user_id, 'status' => 'active']);
        $tokenForA = $user->createToken('mobile', ["church:{$churchA->slug}"])->plainTextToken;

        // On church B's host, with no override, ResolveTenant pins the request to A (the
        // token's claim), so it succeeds as an A member — it never binds B. Were the host to
        // win, it would bind B and the membership gate would 403 (the user is not a B member).
        $this->withToken($tokenForA)
            ->getJson("http://{$churchB->slug}.localhost/api/v1/me")
            ->assertOk();
    }

    public function test_a_token_cannot_be_pointed_at_another_church_via_override(): void
    {
        config(['tenancy.enabled' => true]);
        $churchA = Church::create(['slug' => 'stluke', 'name' => 'St Luke', 'status' => 'active']);
        $churchB = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);

        // Member of BOTH churches — so only the token's church scope differentiates.
        $user = $this->createUser(['email' => 'dual-member@example.com']);
        ChurchUser::create(['church_id' => $churchA->church_id, 'user_id' => $user->user_id, 'status' => 'active']);
        ChurchUser::create(['church_id' => $churchB->church_id, 'user_id' => $user->user_id, 'status' => 'active']);

        $tokenForA = $user->createToken('mobile', ["church:{$churchA->slug}"])->plainTextToken;

        // Its own church → allowed.
        $this->withToken($tokenForA)
            ->getJson("http://{$churchA->slug}.localhost/api/v1/me")
            ->assertOk();

        // Overriding the tenant to church B (the X-Church-Slug header outranks the token
        // claim in ResolveTenant) is rejected, even though the user is a member of B.
        $this->withToken($tokenForA)
            ->withHeader('X-Church-Slug', $churchB->slug)
            ->getJson("http://{$churchB->slug}.localhost/api/v1/me")
            ->assertStatus(403);
    }
}

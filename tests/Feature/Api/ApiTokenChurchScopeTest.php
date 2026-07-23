<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\ChurchUser;
use Tests\Support\EventModuleTestCase;

/**
 * An API token is bound to the church it was issued for (a `church:{id}` ability), and
 * cannot be replayed against another church's host — even for a user who is a member of
 * both churches (which is why the membership gate alone is not enough). Dormant while
 * tenancy is disabled.
 */
class ApiTokenChurchScopeTest extends EventModuleTestCase
{
    public function test_a_token_scoped_to_one_church_is_rejected_on_another(): void
    {
        config(['tenancy.enabled' => true]);
        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);

        // Member of BOTH churches, so only the token's church scope differentiates.
        $user = $this->createUser(['email' => 'dual-member@example.com']);
        ChurchUser::create(['church_id' => $churchA->church_id, 'user_id' => $user->user_id, 'status' => 'active']);
        ChurchUser::create(['church_id' => $churchB->church_id, 'user_id' => $user->user_id, 'status' => 'active']);

        $tokenForA = $user->createToken('mobile', ["church:{$churchA->church_id}"])->plainTextToken;

        // Right church → allowed; wrong church → 403 even though the user is a member there.
        $this->withToken($tokenForA)->getJson("http://{$churchA->slug}.localhost/api/v1/me")->assertOk();
        $this->withToken($tokenForA)->getJson("http://{$churchB->slug}.localhost/api/v1/me")->assertStatus(403);
    }

    public function test_login_scopes_the_token_to_the_resolved_church(): void
    {
        config(['tenancy.enabled' => true]);
        $main = Church::main();
        $churchB = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);
        $user = $this->createUser(['email' => 'login-scope@example.com']); // factory password: 'password'
        ChurchUser::create(['church_id' => $churchB->church_id, 'user_id' => $user->user_id, 'status' => 'active']);

        $login = $this->postJson("http://{$churchB->slug}.localhost/api/v1/login", [
            'email' => 'login-scope@example.com',
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('token');

        // The token was issued on St Mark's host → works there, rejected on the main host.
        $this->withToken($token)->getJson("http://{$churchB->slug}.localhost/api/v1/me")->assertOk();
        $this->withToken($token)->getJson("http://{$main->slug}.localhost/api/v1/me")->assertStatus(403);
    }
}

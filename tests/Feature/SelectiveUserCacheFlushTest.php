<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Services\ForceLogoutService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class SelectiveUserCacheFlushTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    public function test_superadmin_can_flush_selected_users(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'flush-super@example.com',
        ]);
        $targetA = $this->createUser(['email' => 'flush-a@example.com']);
        $targetB = $this->createUser(['email' => 'flush-b@example.com']);

        $this->seedDatabaseSession('flush-session-a', $targetA->user_id);
        $this->seedDatabaseSession('flush-session-b', $targetB->user_id);

        $targetA->update(['remember_token' => 'token-a']);
        $targetB->update(['remember_token' => 'token-b']);

        Cache::put("perms:system:{$targetA->user_id}", collect(['cached-a']), 600);
        Cache::put("perms:system:{$targetB->user_id}", collect(['cached-b']), 600);

        $this->actingAs($super)
            ->post(route('superadmin.sessions.flush-users'), [
                'user_ids' => [$targetA->user_id, $targetB->user_id],
            ])
            ->assertRedirect(route('superadmin.security'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'flush-session-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'flush-session-b']);
        $this->assertNull($targetA->fresh()->remember_token);
        $this->assertNull($targetB->fresh()->remember_token);
        $this->assertNull(Cache::get("perms:system:{$targetA->user_id}"));
        $this->assertNull(Cache::get("perms:system:{$targetB->user_id}"));

        $log = ActivityLog::where('route_name', 'platform.users_cache_flush')->first();
        $this->assertNotNull($log);
        $this->assertSame($super->user_id, $log->user_id);
        $this->assertEqualsCanonicalizing(
            [$targetA->user_id, $targetB->user_id],
            $log->request_input['target_user_ids'] ?? []
        );
    }

    public function test_flush_selected_users_leaves_other_sessions_intact(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'flush-scope-super@example.com',
        ]);
        $target = $this->createUser(['email' => 'flush-scope-target@example.com']);
        $other = $this->createUser(['email' => 'flush-scope-other@example.com']);

        $this->seedDatabaseSession('flush-scope-target', $target->user_id);
        $this->seedDatabaseSession('flush-scope-other', $other->user_id);

        $other->update(['remember_token' => 'keep-me']);
        Cache::put("perms:system:{$other->user_id}", collect(['keep']), 600);

        $this->actingAs($super)
            ->post(route('superadmin.sessions.flush-users'), [
                'user_ids' => [$target->user_id],
            ])
            ->assertRedirect(route('superadmin.security'));

        $this->assertDatabaseMissing('sessions', ['id' => 'flush-scope-target']);
        $this->assertDatabaseHas('sessions', ['id' => 'flush-scope-other', 'user_id' => $other->user_id]);
        $this->assertSame('keep-me', $other->fresh()->remember_token);
        $this->assertNotNull(Cache::get("perms:system:{$other->user_id}"));
    }

    public function test_flush_selected_users_requires_at_least_one_user(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'flush-validate-super@example.com',
        ]);

        $this->actingAs($super)
            ->post(route('superadmin.sessions.flush-users'), [
                'user_ids' => [],
            ])
            ->assertSessionHasErrors('user_ids');
    }

    public function test_non_superadmin_cannot_flush_selected_users(): void
    {
        $user = $this->createUser(['email' => 'flush-denied@example.com']);
        $target = $this->createUser(['email' => 'flush-denied-target@example.com']);

        $this->actingAs($user)
            ->post(route('superadmin.sessions.flush-users'), [
                'user_ids' => [$target->user_id],
            ])
            ->assertForbidden();
    }

    public function test_logout_users_service_clears_file_sessions_for_selected_users(): void
    {
        config(['session.driver' => 'file']);

        $target = $this->createUser(['email' => 'flush-file-target@example.com']);
        $other = $this->createUser(['email' => 'flush-file-other@example.com']);

        $directory = storage_path('framework/sessions');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $targetSessionId = 'sess-target';
        $otherSessionId = 'sess-other';

        $targetPayload = serialize(['login_web_test' => $target->user_id, '_token' => 'abc']);
        $otherPayload = serialize(['login_web_test' => $other->user_id, '_token' => 'def']);

        file_put_contents($directory.'/'.$targetSessionId, $targetPayload);
        file_put_contents($directory.'/'.$otherSessionId, $otherPayload);

        $result = ForceLogoutService::logoutUsers([$target->user_id]);

        $this->assertSame(1, $result['sessions_cleared']);
        $this->assertFileDoesNotExist($directory.'/'.$targetSessionId);
        $this->assertFileExists($directory.'/'.$otherSessionId);

        @unlink($directory.'/'.$otherSessionId);
    }

    public function test_logout_users_service_clears_permission_cache(): void
    {
        $target = $this->createUser(['email' => 'flush-cache-target@example.com']);

        Cache::put("perms:system:{$target->user_id}", collect(['cached']), 600);

        ForceLogoutService::logoutUsers([$target->user_id]);

        $this->assertNull(Cache::get("perms:system:{$target->user_id}"));
    }

    public function test_security_page_shows_flush_users_form(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'flush-ui-super@example.com',
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.security'))
            ->assertOk()
            ->assertSee(__('pages.flush_users_title'), false)
            ->assertSee('name="user_ids[]"', false);
    }

    private function seedDatabaseSession(string $sessionId, int $userId): void
    {
        if (! Schema::hasTable('sessions')) {
            $this->markTestSkipped('sessions table is not available.');
        }

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode(serialize(['login_web_test' => $userId])),
            'last_activity' => time(),
        ]);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\UserNotification;
use App\Services\AnnouncementService;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EventModuleTestCase;

class MobileApiV1Test extends EventModuleTestCase
{
    public function test_design_tokens_are_public(): void
    {
        $this->getJson('/api/v1/design-tokens')
            ->assertOk()
            ->assertJsonPath('meta.name', 'khoddam')
            ->assertJsonStructure(['light' => ['primary'], 'dark' => ['primary']]);
    }

    public function test_login_issues_sanctum_token(): void
    {
        $user = $this->createUser([
            'email' => 'mobile-login@example.com',
            'password' => Hash::make('password'),
            'is_verified' => true,
            'registration_completed' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['user_id', 'email', 'display_name']]);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'mobile-login@example.com');
    }

    public function test_login_rejects_bad_password(): void
    {
        $user = $this->createUser([
            'email' => 'mobile-bad@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_notifications_and_mark_all_read(): void
    {
        $user = $this->createUser(['email' => 'mobile-notif@example.com']);
        Sanctum::actingAs($user);

        UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'assignment_deadline',
            'title' => 'Due soon',
            'body' => 'Submit',
            'dedupe_key' => 'mobile:api:1',
        ]);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Due soon'])
            ->assertJsonPath('meta.unread_count', 1);

        $this->postJson('/api/v1/notifications/mark-all-read')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_announcements_inbox(): void
    {
        $course = $this->createCourse();
        $studentRole = $this->createRole('student');
        $adminRole = $this->courseRoleWithPermissions($course, 'ann-api-admin', [
            'announcement.manage', 'announcement.publish', 'roster.view',
        ]);
        $admin = $this->createUser(['email' => 'ann-api-admin@example.com']);
        $student = $this->createUser(['email' => 'ann-api-student@example.com']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $service = app(AnnouncementService::class);
        $announcement = $service->createDraft($admin, [
            'course_id' => $course->course_id,
            'title' => 'Mobile Announcement',
            'body' => 'Hello mobile',
            'target_mode' => Announcement::TARGET_COURSE,
            'channels' => [
                Announcement::CHANNEL_HOMEPAGE => true,
            ],
        ]);
        $service->publish($announcement, $admin);

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/announcements')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Mobile Announcement']);
    }

    public function test_attendance_mine(): void
    {
        $user = $this->createUser(['email' => 'mobile-att@example.com']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/attendance/mine')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['overall', 'monthly']]);
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->createUser([
            'email' => 'mobile-logout@example.com',
            'password' => Hash::make('password'),
        ]);

        $login = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->user_id,
        ]);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_me_preferences_update(): void
    {
        $user = $this->createUser(['email' => 'mobile-prefs@example.com']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/preferences', [
            'communication_locale' => 'en',
        ])->assertOk()->assertJsonPath('data.communication_locale', 'en');
    }
}

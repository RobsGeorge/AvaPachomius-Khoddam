<?php

namespace Tests\Feature;

use App\Models\PortalSettings;
use App\Models\User;
use App\Services\CourseContextService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

/**
 * End-to-end path for students hard-blocked by the profile-photo gate:
 * redirect → profile upload → pending unlock → platform usable again.
 */
class ProfilePhotoGatePathTest extends EventModuleTestCase
{
    private function hardBlockedStudent(array $userOverrides = []): array
    {
        $timezone = config('attendance.timezone', config('app.timezone'));
        PortalSettings::current()->forceFill([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled_at' => now($timezone)->subDays(10),
        ])->save();

        $studentRole = $this->createRole('student');
        $student = $this->createUser(array_merge([
            'email' => 'photo-gate-path@example.com',
            'profile_photo' => '',
            'registration_completed' => true,
        ], $userOverrides));
        $course = $this->createCourse(['title' => 'Photo Gate Path Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $student->forceFill([
            'profile_photo_grace_started_at' => now($timezone)->subDays(4),
        ])->save();

        return [$student->fresh(), $course, $studentRole];
    }

    public function test_hard_blocked_student_can_upload_photo_without_course_context(): void
    {
        Storage::fake('public');
        [$student, $course, $studentRole] = $this->hardBlockedStudent();

        // Simulate post-force-logout: no course selected in session, multiple courses exist
        // so auto-select cannot rescue the upload.
        $second = $this->createCourse(['title' => 'Second Course For Context']);
        $this->assignCourseRole($student, $second, $studentRole);

        $this->actingAs($student);
        app(CourseContextService::class)->clearCurrentCourse();

        $this->get(route('dashboard'))
            ->assertRedirect(route('profile'));

        $profile = $this->get(route('profile'));
        $profile->assertOk();
        $profile->assertSee(__('pages.profile_photo_required_locked'), false);
        $this->assertStringContainsString('no-store', (string) $profile->headers->get('Cache-Control'));

        $file = UploadedFile::fake()->image('face.jpg', 400, 400);

        $this->put(route('profile.picture.update'), [
            'profile_photo' => $file,
        ])->assertRedirect(route('profile'));

        $student->refresh();
        $this->assertTrue($student->hasProfilePhoto());
        $this->assertTrue($student->isProfilePhotoPending());
        $this->assertFalse(app(\App\Services\ProfilePhotoGateService::class)->isHardBlocked($student));

        // After unlock, course context may still be required — that is a separate picker,
        // not the photo gate. Assert we are no longer forced back to profile.
        $after = $this->get(route('dashboard'));
        $this->assertNotEquals(route('profile'), $after->headers->get('Location'));
        if ($after->isRedirect()) {
            $this->assertTrue(
                str_contains((string) $after->headers->get('Location'), 'courses/select')
                || str_contains((string) $after->headers->get('Location'), 'services/select')
            );
        } else {
            $after->assertOk();
        }
    }

    public function test_hard_blocked_student_upload_unlocks_when_service_context_also_missing(): void
    {
        if (! \App\Models\ChurchService::tableReady()) {
            $this->markTestSkipped('Service layer not available in this schema.');
        }

        Storage::fake('public');
        [$student] = $this->hardBlockedStudent([
            'email' => 'photo-gate-service-path@example.com',
        ]);

        // Two selectable services without a current selection (post session flush).
        $serviceA = $this->createService(['title' => 'Service A Path', 'title_en' => 'Service A Path']);
        $serviceB = $this->createService(['title' => 'Service B Path', 'title_en' => 'Service B Path']);
        $this->assignServiceRole($student, $serviceA, null, false, true);
        $this->assignServiceRole($student, $serviceB, null, false, true);

        $this->actingAs($student);
        app(\App\Services\ServiceContextService::class)->clearCurrentService();
        app(CourseContextService::class)->clearCurrentCourse();

        $this->put(route('profile.picture.update'), [
            'profile_photo' => UploadedFile::fake()->image('unlock.jpg', 300, 300),
        ])->assertRedirect(route('profile'));

        $student->refresh();
        $this->assertSame(User::PHOTO_STATUS_PENDING, $student->profile_photo_status);
        $this->assertFalse(app(\App\Services\ProfilePhotoGateService::class)->isHardBlocked($student));
    }

    public function test_login_and_profile_send_no_store_cache_headers(): void
    {
        $login = $this->get(route('login'));
        $login->assertOk();
        $this->assertStringContainsString('no-store', (string) $login->headers->get('Cache-Control'));

        [$student] = $this->hardBlockedStudent([
            'email' => 'photo-gate-headers@example.com',
        ]);

        $profile = $this->actingAs($student)->get(route('profile'));
        $profile->assertOk();
        $this->assertStringContainsString('no-store', (string) $profile->headers->get('Cache-Control'));
    }

    public function test_token_mismatch_redirects_with_friendly_message_instead_of_page_expired(): void
    {
        // CSRF middleware is skipped while runningUnitTests(), so exercise the
        // exception handler directly (same path production hits on 419).
        $request = \Illuminate\Http\Request::create('/login', 'POST', [
            '_token' => 'stale-token-after-force-logout',
            'email' => 'anyone@example.com',
            'password' => 'secret',
        ]);
        $request->headers->set('referer', route('login'));
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('login'), $response->headers->get('Location'));
        $this->assertSame(__('auth.page_expired'), $session->get('warning'));
        $this->assertNotSame('stale-token-after-force-logout', $session->token());
    }

    public function test_token_mismatch_on_photo_upload_redirects_to_profile_referer(): void
    {
        [$student] = $this->hardBlockedStudent([
            'email' => 'photo-gate-csrf-handler@example.com',
        ]);
        $this->actingAs($student);

        $request = \Illuminate\Http\Request::create('/profile/picture', 'POST', [
            '_token' => 'stale-csrf-token',
            '_method' => 'PUT',
        ]);
        $request->headers->set('referer', route('profile'));
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('profile'), $response->headers->get('Location'));
        $this->assertSame(__('auth.page_expired'), $session->get('warning'));
    }
}
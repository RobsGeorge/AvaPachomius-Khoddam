<?php

namespace Tests\Feature;

use App\Models\PortalSettings;
use App\Models\User;
use App\Services\ProfilePhotoGateService;
use Tests\Support\EventModuleTestCase;

class ProfilePhotoAdminTest extends EventModuleTestCase
{
    public function test_admin_can_approve_pending_photo(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');

        $admin = $this->createUser(['email' => 'photo-admin@example.com']);
        $student = $this->createUser([
            'email' => 'photo-student@example.com',
            'profile_photo' => 'profile_photos/test.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
        ]);
        $course = $this->createCourse(['title' => 'Photo Course']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.approve', $student))
            ->assertRedirect();

        $student->refresh();
        $this->assertTrue($student->isProfilePhotoApproved());
    }

    public function test_admin_can_reset_grace_and_clear_photo(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');

        $admin = $this->createUser(['email' => 'reset-admin@example.com']);
        $student = $this->createUser([
            'email' => 'reset-student@example.com',
            'profile_photo' => 'profile_photos/old.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_REJECTED,
            'profile_photo_grace_started_at' => now()->subDays(5),
        ]);
        $course = $this->createCourse(['title' => 'Reset Course']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.reset-grace', $student))
            ->assertRedirect();

        $student->refresh();
        $this->assertSame('', $student->profile_photo);
        $this->assertNull($student->profile_photo_grace_started_at);
        $this->assertNull($student->profile_photo_status);
    }

    public function test_grace_days_setting_changes_deadline(): void
    {
        PortalSettings::current()->update(['profile_photo_grace_days' => 7]);

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'grace-days-student@example.com',
            'profile_photo' => '',
        ]);
        $course = $this->createCourse(['title' => 'Grace Days Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $timezone = config('attendance.timezone', config('app.timezone'));
        $started = now($timezone);
        $student->forceFill([
            'profile_photo_grace_started_at' => $started,
        ])->save();

        $gate = app(ProfilePhotoGateService::class);
        $deadline = $gate->deadlineFor($student->fresh());

        $this->assertNotNull($deadline);
        $this->assertSame(
            $started->copy()->addDays(7)->format('Y-m-d'),
            $deadline->format('Y-m-d')
        );
    }

    public function test_admin_report_page_loads(): void
    {
        $adminRole = $this->createRole('admin');
        $admin = $this->createUser(['email' => 'report-admin@example.com']);
        $course = $this->createCourse(['title' => 'Report Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $this->actingAs($admin)
            ->get(route('admin.profile-photos.index'))
            ->assertOk()
            ->assertSee(__('profile_photos.report_title'));
    }

    public function test_gate_disabled_hides_banners_and_unlocks_overdue_student(): void
    {
        PortalSettings::current()->update(['profile_photo_gate_enabled' => false]);

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'gate-off-student@example.com',
            'profile_photo' => '',
            'profile_photo_grace_started_at' => now()->subDays(10),
        ]);
        $course = $this->createCourse(['title' => 'Gate Off Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $gate = app(ProfilePhotoGateService::class);

        $this->assertFalse($gate->shouldShowWarningBanner($student));
        $this->assertFalse($gate->shouldShowPendingBanner($student));
        $this->assertFalse($gate->shouldShowRejectedBanner($student));
        $this->assertFalse($gate->isHardBlocked($student));

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('pages.profile_photo_required_banner', ['deadline' => '']));
    }

    public function test_re_enabling_gate_gives_fresh_grace_period(): void
    {
        $settings = PortalSettings::current();
        $settings->update([
            'profile_photo_gate_enabled' => false,
            'profile_photo_gate_enabled_at' => now()->subDays(20),
        ]);

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'gate-restart-student@example.com',
            'profile_photo' => '',
            'profile_photo_grace_started_at' => now()->subDays(10),
        ]);
        $course = $this->createCourse(['title' => 'Gate Restart Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        app(\App\Services\ProfilePhotoAdminService::class)->updateSettings(3, true);

        $gate = app(ProfilePhotoGateService::class);
        $gate->ensureGraceStarted($student->fresh());

        $student->refresh();
        $this->assertNotNull($student->profile_photo_grace_started_at);
        $this->assertTrue($gate->shouldShowWarningBanner($student));
        $this->assertFalse($gate->isHardBlocked($student));
    }

    public function test_admin_can_disable_gate_via_settings_form(): void
    {
        $adminRole = $this->createRole('admin');
        $admin = $this->createUser(['email' => 'gate-toggle-admin@example.com']);
        $course = $this->createCourse(['title' => 'Toggle Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        PortalSettings::current()->update(['profile_photo_gate_enabled' => true]);

        $this->actingAs($admin)
            ->put(route('admin.profile-photos.settings'), [
                'profile_photo_grace_days' => 5,
                'profile_photo_gate_enabled' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse(PortalSettings::current()->fresh()->profile_photo_gate_enabled);
    }
}

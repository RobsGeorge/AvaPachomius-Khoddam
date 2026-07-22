<?php

namespace Tests\Feature;

use App\Models\PortalSettings;
use App\Services\ImpersonationService;
use Tests\Support\EventModuleTestCase;

class ProfilePhotoGateUxTest extends EventModuleTestCase
{
    private function hardBlockedStudent(): array
    {
        $timezone = config('attendance.timezone', config('app.timezone'));
        PortalSettings::current()->forceFill([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled_at' => now($timezone)->subDays(10),
        ])->save();

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'photo-gate-ux-student@example.com',
            'profile_photo' => '',
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Photo Gate UX Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $student->forceFill([
            'profile_photo_grace_started_at' => now($timezone)->subDays(4),
        ])->save();

        return [$student, $course];
    }

    public function test_hard_blocked_student_is_redirected_but_profile_stays_usable_for_qr(): void
    {
        [$student] = $this->hardBlockedStudent();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile'));

        $this->actingAs($student)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(__('pages.profile_photo_required_locked'), false)
            ->assertSee(__('pages.qr_attendance_title'), false)
            ->assertSee('id="qrModal"', false)
            ->assertDontSee('id="photoRequiredModal"', false);
    }

    public function test_hard_blocked_student_sees_locked_banner_on_layout(): void
    {
        [$student] = $this->hardBlockedStudent();

        $this->actingAs($student)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('profile-photo-banner', false)
            ->assertSee(__('pages.profile_photo_required_locked'), false);
    }

    public function test_impersonating_hard_blocked_student_can_exit_without_photo_modal_trap(): void
    {
        [$student] = $this->hardBlockedStudent();

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'photo-gate-ux-super@example.com',
            'registration_completed' => true,
        ]);

        $this->actingAs($super)
            ->post(route('superadmin.impersonate'), ['user_id' => $student->user_id])
            ->assertRedirect(route('dashboard'));

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee(__('pages.impersonate_exit'), false)
            ->assertSee('impersonation-banner', false)
            ->assertSee('sticky-top', false)
            ->assertDontSee('id="photoRequiredModal"', false);

        $this->post(route('superadmin.impersonate.stop'))
            ->assertRedirect();

        $this->assertSame($super->user_id, auth()->id());
        $this->assertFalse(ImpersonationService::isActive());
    }
}

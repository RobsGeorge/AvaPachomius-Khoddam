<?php

namespace Tests\Feature;

use App\Models\PortalSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class ProfilePhotoGraceStudentTest extends EventModuleTestCase
{
    public function test_grace_student_can_load_dashboard_and_profile(): void
    {
        $timezone = config('attendance.timezone', config('app.timezone'));
        PortalSettings::current()->forceFill([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled_at' => now($timezone)->subDays(10),
        ])->save();

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'grace-dash@example.com',
            'profile_photo' => '',
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Grace Dash Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $student->forceFill([
            'profile_photo_grace_started_at' => now($timezone)->subDay(),
            'profile_photo_deadline_at' => null,
        ])->save();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('pages.profile_photo_required_link'), false);

        $this->actingAs($student)
            ->get(route('profile'))
            ->assertOk();
    }

    public function test_impersonating_grace_student_loads_dashboard(): void
    {
        $timezone = config('attendance.timezone', config('app.timezone'));
        PortalSettings::current()->forceFill([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled_at' => now($timezone)->subDays(10),
        ])->save();

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'grace-imp@example.com',
            'profile_photo' => '',
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Grace Imp Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $student->forceFill([
            'profile_photo_grace_started_at' => now($timezone)->subDay(),
        ])->save();

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'grace-imp-super@example.com',
            'registration_completed' => true,
        ]);

        $this->actingAs($super)
            ->post(route('superadmin.impersonate'), ['user_id' => $student->user_id])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('pages.impersonate_banner_title'), false)
            ->assertSee(__('pages.profile_photo_required_link'), false);
    }

    public function test_grace_student_with_legacy_zero_dates_loads_dashboard(): void
    {
        PortalSettings::current()->forceFill([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled_at' => now()->subDays(10),
        ])->save();

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'grace-zero@example.com',
            'profile_photo' => '',
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Grace Zero Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        DB::table('user')->where('user_id', $student->user_id)->update([
            'profile_photo_grace_started_at' => '0000-00-00 00:00:00',
            'profile_photo_deadline_at' => '0000-00-00 00:00:00',
        ]);

        $this->actingAs($student->fresh())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_bad_gate_enabled_at_does_not_500_grace_student(): void
    {
        PortalSettings::current()->forceFill([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
        ])->save();

        if (Schema::hasColumn('portal_settings', 'profile_photo_gate_enabled_at')) {
            DB::table('portal_settings')->where('id', 1)->update([
                'profile_photo_gate_enabled_at' => '0000-00-00 00:00:00',
            ]);
        }

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'grace-bad-enabled@example.com',
            'profile_photo' => '',
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Grace Bad Enabled Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $student->forceFill([
            'profile_photo_grace_started_at' => now()->subDay(),
        ])->save();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\User;
use App\Services\ProfilePhotoGateService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class AnnouncementModuleTest extends EventModuleTestCase
{
    public function test_instructor_can_publish_course_announcement_to_students(): void
    {
        Mail::fake();

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');

        $instructor = $this->createUser(['email' => 'announce-instructor@example.com']);
        $student = $this->createUser(['email' => 'announce-student@example.com', 'profile_photo' => '']);
        $course = $this->createCourse(['title' => 'Announce Course']);

        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($instructor)
            ->post(route('announcements.manage.store'), [
                'title' => 'Important update',
                'body' => 'Please review the new schedule.',
                'target_mode' => Announcement::TARGET_COURSE,
                'course_id' => $course->course_id,
                'channels' => [
                    Announcement::CHANNEL_HOMEPAGE => true,
                    Announcement::CHANNEL_EMAIL => true,
                ],
            ])
            ->assertRedirect();

        $announcement = Announcement::query()->first();
        $this->assertNotNull($announcement);

        $this->actingAs($instructor)
            ->post(route('announcements.manage.publish', $announcement))
            ->assertRedirect();

        $this->assertDatabaseHas('announcement_deliveries', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $student->user_id,
        ]);

        Mail::assertSent(AnnouncementMail::class);

        $this->actingAs($student)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Important update');

        $delivery = AnnouncementDelivery::query()->where('user_id', $student->user_id)->first();
        $this->assertNotNull($delivery);

        $this->actingAs($student)
            ->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('Please review the new schedule.');

        $this->assertNotNull($delivery->fresh()->read_at);
    }

    public function test_student_without_photo_is_hard_blocked_after_grace_period(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'photo-gate-student@example.com',
            'profile_photo' => '',
        ]);
        $course = $this->createCourse(['title' => 'Gate Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $timezone = config('attendance.timezone', config('app.timezone'));
        // The gate must have been enabled *before* the grace started; otherwise the
        // service resets a grace that predates gate_enabled_at (you cannot be past-grace
        // for a gate that was only just switched on). PortalSettings::current() defaults
        // gate_enabled_at to now(), so pin it into the past for this scenario.
        $settings = \App\Models\PortalSettings::current();
        $settings->forceFill([
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled' => true,
            'profile_photo_gate_enabled_at' => now($timezone)->subDays(10),
        ])->save();

        $student->forceFill([
            'profile_photo_grace_started_at' => now($timezone)->subDays(4),
        ])->save();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile'));

        $this->actingAs($student)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(__('pages.profile_photo_required_locked'));
    }

    public function test_grace_period_starts_on_first_visit_for_student_without_photo(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'grace-start-student@example.com',
            'profile_photo' => '',
        ]);
        $course = $this->createCourse(['title' => 'Grace Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($student)->get(route('dashboard'))->assertOk();

        $student->refresh();
        $this->assertNotNull($student->profile_photo_grace_started_at);

        $gate = app(ProfilePhotoGateService::class);
        $this->assertTrue($gate->shouldShowWarningBanner($student));
        $this->assertFalse($gate->isHardBlocked($student));
    }

    public function test_announcements_manage_index_is_not_captured_by_show_route(): void
    {
        $adminRole = $this->createRole('admin');
        $admin = $this->createUser(['email' => 'announce-manage-admin@example.com']);
        $course = $this->createCourse(['title' => 'Manage Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $this->actingAs($admin)
            ->get(route('announcements.manage.index'))
            ->assertOk()
            ->assertSee(__('announcements.manage_title'));
    }
}

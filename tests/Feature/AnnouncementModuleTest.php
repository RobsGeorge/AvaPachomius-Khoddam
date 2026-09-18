<?php

namespace Tests\Feature;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationScannerService;
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

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $student->user_id,
            'type' => UserNotification::TYPE_ADMIN_ANNOUNCEMENT,
            'title' => 'Important update',
            'dedupe_key' => "admin_announcement:{$announcement->announcement_id}:user:{$student->user_id}",
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

    public function test_publish_keeps_deliveries_when_recipient_notification_fails(): void
    {
        Mail::fake();

        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');

        $instructor = $this->createUser(['email' => 'announce-resilient-instructor@example.com']);
        $student = $this->createUser(['email' => 'announce-resilient-student@example.com']);
        $course = $this->createCourse(['title' => 'Resilient Announce Course']);

        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->mock(NotificationScannerService::class, function ($mock) {
            $mock->shouldReceive('notifyAnnouncement')
                ->once()
                ->andThrow(new \RuntimeException('forced notification failure'));
        });

        $this->actingAs($instructor)
            ->post(route('announcements.manage.store'), [
                'title' => 'Still delivered',
                'body' => 'Delivery must survive notification errors.',
                'target_mode' => Announcement::TARGET_COURSE,
                'course_id' => $course->course_id,
                'channels' => [
                    Announcement::CHANNEL_HOMEPAGE => true,
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

        $this->assertSame(Announcement::STATUS_PUBLISHED, $announcement->fresh()->status);
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

    public function test_expired_published_announcement_is_grouped_as_finished(): void
    {
        Mail::fake();

        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');

        $instructor = $this->createUser(['email' => 'announce-expired-instructor@example.com']);
        $student = $this->createUser(['email' => 'announce-expired-student@example.com']);
        $course = $this->createCourse(['title' => 'Expired Announce Course']);

        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $timezone = config('attendance.timezone', config('app.timezone'));

        $this->actingAs($instructor)
            ->post(route('announcements.manage.store'), [
                'title' => 'Past deadline notice',
                'body' => 'This should move to Finished after the end date.',
                'target_mode' => Announcement::TARGET_COURSE,
                'course_id' => $course->course_id,
                'banner_starts_at' => now($timezone)->subDays(3)->format('Y-m-d\TH:i'),
                'banner_ends_at' => now($timezone)->subDay()->format('Y-m-d\TH:i'),
                'channels' => [
                    Announcement::CHANNEL_HOMEPAGE => true,
                ],
            ])
            ->assertRedirect();

        $announcement = Announcement::query()->first();
        $this->assertNotNull($announcement);

        $announcement->forceFill([
            'banner_starts_at' => now($timezone)->subDays(3),
            'banner_ends_at' => now($timezone)->subDay(),
        ])->save();

        $this->actingAs($instructor)
            ->post(route('announcements.manage.publish', $announcement))
            ->assertRedirect();

        $announcement->refresh();
        $this->assertFalse($announcement->isCurrentlyVisible());
        $this->assertTrue($announcement->isFinished());

        $inbox = app(\App\Services\AnnouncementService::class)->studentInbox($student);
        $this->assertCount(1, $inbox);
        $this->assertTrue($inbox->first()->announcement->isFinished());

        $this->actingAs($student)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee(__('announcements.section_finished'))
            ->assertSee('Past deadline notice')
            ->assertViewHas('openDeliveries', fn ($deliveries) => $deliveries->isEmpty())
            ->assertViewHas('finishedDeliveries', fn ($deliveries) => $deliveries->count() === 1);

        $this->actingAs($student)
            ->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('This should move to Finished after the end date.');

        $this->actingAs($instructor)
            ->get(route('announcements.manage.index'))
            ->assertOk()
            ->assertSee(__('announcements.section_finished'))
            ->assertSee('Past deadline notice')
            ->assertViewHas('finishedItems', fn ($items) => $items->contains('announcement_id', $announcement->announcement_id));
    }

    public function test_instructor_can_unpublish_announcement(): void
    {
        Mail::fake();

        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');

        $instructor = $this->createUser(['email' => 'announce-unpublish-instructor@example.com']);
        $student = $this->createUser(['email' => 'announce-unpublish-student@example.com']);
        $course = $this->createCourse(['title' => 'Unpublish Course']);

        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($instructor)
            ->post(route('announcements.manage.store'), [
                'title' => 'Will be unpublished',
                'body' => 'Students should lose access after unpublish.',
                'target_mode' => Announcement::TARGET_COURSE,
                'course_id' => $course->course_id,
                'channels' => [
                    Announcement::CHANNEL_HOMEPAGE => true,
                ],
            ])
            ->assertRedirect();

        $announcement = Announcement::query()->first();
        $this->assertNotNull($announcement);

        $this->actingAs($instructor)
            ->post(route('announcements.manage.publish', $announcement))
            ->assertRedirect();

        $this->actingAs($student)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Will be unpublished');

        $this->actingAs($instructor)
            ->post(route('announcements.manage.unpublish', $announcement))
            ->assertRedirect(route('announcements.manage.edit', $announcement));

        $this->assertSame(Announcement::STATUS_DRAFT, $announcement->fresh()->status);

        $this->actingAs($student)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertViewHas('openDeliveries', fn ($deliveries) => $deliveries->isEmpty())
            ->assertViewHas('finishedDeliveries', fn ($deliveries) => $deliveries->isEmpty());
    }

    public function test_instructor_can_clone_announcement_with_new_dates(): void
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'announce-clone-instructor@example.com']);
        $course = $this->createCourse(['title' => 'Clone Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $timezone = config('attendance.timezone', config('app.timezone'));

        $this->actingAs($instructor)
            ->post(route('announcements.manage.store'), [
                'title' => 'Original announcement',
                'body' => 'Clone me completely.',
                'target_mode' => Announcement::TARGET_COURSE,
                'course_id' => $course->course_id,
                'banner_starts_at' => now($timezone)->subDays(10)->format('Y-m-d\TH:i'),
                'banner_ends_at' => now($timezone)->subDays(5)->format('Y-m-d\TH:i'),
                'channels' => [
                    Announcement::CHANNEL_HOMEPAGE => true,
                    Announcement::CHANNEL_EMAIL => true,
                ],
            ])
            ->assertRedirect();

        $source = Announcement::query()->first();
        $this->assertNotNull($source);

        $newStart = now($timezone)->addDay()->seconds(0)->format('Y-m-d H:i:s');
        $newEnd = now($timezone)->addDays(7)->seconds(0)->format('Y-m-d H:i:s');

        $response = $this->actingAs($instructor)
            ->post(route('announcements.manage.clone', $source), [
                'banner_starts_at' => $newStart,
                'banner_ends_at' => $newEnd,
            ]);

        $clone = Announcement::query()
            ->where('announcement_id', '!=', $source->announcement_id)
            ->first();

        $this->assertNotNull($clone);
        $response->assertRedirect(route('announcements.manage.edit', $clone));

        $this->assertSame(Announcement::STATUS_DRAFT, $clone->status);
        $this->assertSame('Original announcement', $clone->title);
        $this->assertSame('Clone me completely.', $clone->body);
        $this->assertSame($source->course_id, $clone->course_id);
        $this->assertTrue($clone->hasChannel(Announcement::CHANNEL_HOMEPAGE));
        $this->assertTrue($clone->hasChannel(Announcement::CHANNEL_EMAIL));
        $this->assertNotNull($clone->banner_starts_at);
        $this->assertNotNull($clone->banner_ends_at);
        $this->assertSame($newStart, $clone->banner_starts_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'));
        $this->assertSame($newEnd, $clone->banner_ends_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'));
        $this->assertNull($clone->published_at);
    }
}

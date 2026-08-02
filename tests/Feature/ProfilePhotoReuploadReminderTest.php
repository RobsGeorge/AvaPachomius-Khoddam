<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\PortalSettings;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ProfilePhotoReuploadReminderService;
use App\Services\ScheduledTaskRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ProfilePhotoReuploadReminderTest extends EventModuleTestCase
{
    public function test_reminder_fires_once_for_a_rejection_cycle(): void
    {
        Mail::fake();
        PortalSettings::current()->update(['profile_photo_reupload_reminder_days' => 2]);

        $student = $this->rejectedStudent(now()->subDays(3));
        $service = app(ProfilePhotoReuploadReminderService::class);

        $this->assertSame(1, $service->scan());
        $this->assertSame(0, $service->scan());

        $notifications = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'profile_photo_reupload_reminder')
            ->get();

        $this->assertCount(1, $notifications);
        $this->assertStringContainsString(
            (string) $student->profile_photo_reviewed_at->getTimestamp(),
            $notifications->first()->dedupe_key
        );
        Mail::assertSent(NotificationMail::class, 1);
    }

    public function test_reminder_does_not_fire_after_student_reuploads(): void
    {
        Mail::fake();
        PortalSettings::current()->update(['profile_photo_reupload_reminder_days' => 2]);

        $student = $this->rejectedStudent(now()->subDays(3));
        $student->forceFill([
            'profile_photo' => 'profile_photos/replacement.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
            'profile_photo_uploaded_at' => now(),
        ])->save();

        $this->assertSame(0, app(ProfilePhotoReuploadReminderService::class)->scan());
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $student->user_id,
            'type' => 'profile_photo_reupload_reminder',
        ]);
        Mail::assertNothingSent();
    }

    public function test_reminder_respects_configured_threshold(): void
    {
        Mail::fake();
        PortalSettings::current()->update(['profile_photo_reupload_reminder_days' => 5]);

        $student = $this->rejectedStudent(now()->subDays(3));
        $service = app(ProfilePhotoReuploadReminderService::class);

        $this->assertSame(0, $service->scan());

        $student->forceFill(['profile_photo_reviewed_at' => now()->subDays(6)])->save();

        $this->assertSame(1, $service->scan());
    }

    public function test_settings_form_validates_and_saves_reminder_days(): void
    {
        $adminRole = $this->createRole('admin');
        $admin = $this->createUser(['email' => 'reminder-settings-admin@example.com']);
        $course = $this->createCourse(['title' => 'Reminder Settings Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $this->actingAs($admin)
            ->from(route('admin.profile-photos.index'))
            ->put(route('admin.profile-photos.settings'), [
                'profile_photo_grace_days' => 5,
                'profile_photo_reupload_reminder_days' => 31,
                'profile_photo_gate_enabled' => '1',
            ])
            ->assertRedirect(route('admin.profile-photos.index'))
            ->assertSessionHasErrors('profile_photo_reupload_reminder_days');

        $this->actingAs($admin)
            ->put(route('admin.profile-photos.settings'), [
                'profile_photo_grace_days' => 5,
                'profile_photo_reupload_reminder_days' => 7,
                'profile_photo_gate_enabled' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(7, PortalSettings::current()->fresh()->profile_photo_reupload_reminder_days);
    }

    public function test_reupload_reminder_command_is_scheduled_daily(): void
    {
        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);

        $event = collect($schedule->events())
            ->first(fn ($candidate) => $candidate->description === 'profile_photos.scan_reupload_reminders');

        $this->assertNotNull($event);
        $this->assertStringContainsString('profile-photos:scan-reupload-reminders', $event->command);
        $this->assertSame('0 9 * * *', $event->expression);
    }

    private function rejectedStudent(\DateTimeInterface $reviewedAt): User
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'reupload-reminder-'.uniqid().'@example.com',
            'profile_photo' => '',
            'profile_photo_status' => User::PHOTO_STATUS_REJECTED,
            'profile_photo_reviewed_at' => $reviewedAt,
        ]);
        $course = $this->createCourse(['title' => 'Photo Reminder Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        return $student->fresh();
    }
}

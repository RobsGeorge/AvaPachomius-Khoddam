<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\ActivityLog;
use App\Models\PortalSettings;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ProfilePhotoGateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

class ProfilePhotoAdminTest extends EventModuleTestCase
{
    public function test_admin_can_approve_pending_photo(): void
    {
        Mail::fake();

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

        Mail::assertSent(\App\Mail\ProfilePhotoApprovedMail::class, function ($mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->dashboardUrl === route('dashboard');
        });

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'profile_photo.approved',
        ]);

        $audit = ActivityLog::withoutGlobalScopes()
            ->where('route_name', 'profile_photo.approved')
            ->latest('activity_log_id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->user_id, $audit->request_input['actor_user_id'] ?? null);
        $this->assertSame($student->user_id, $audit->request_input['target_user_id'] ?? null);
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

        $audit = ActivityLog::query()->where('route_name', 'profile_photo.grace_reset')->latest('activity_log_id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->user_id, $audit->request_input['actor_user_id'] ?? null);
        $this->assertSame($student->user_id, $audit->request_input['target_user_id'] ?? null);
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
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'report-admin@example.com']);
        $course = $this->createCourse(['title' => 'Report Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $pending = $this->createUser([
            'email' => 'report-pending@example.com',
            'profile_photo' => 'profile_photos/pending.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
        ]);
        $overdue = $this->createUser([
            'email' => 'report-overdue@example.com',
            'profile_photo' => '',
            'profile_photo_grace_started_at' => now()->subDays(10),
        ]);
        $this->assignCourseRole($pending, $course, $studentRole);
        $this->assignCourseRole($overdue, $course, $studentRole);

        PortalSettings::current()->update([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled_at' => now()->subDays(30),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.profile-photos.index'))
            ->assertOk()
            ->assertSee(__('profile_photos.report_title'))
            ->assertSee(__('profile_photos.status_pending_review'))
            ->assertSee(__('profile_photos.status_overdue'))
            ->assertSee('id="profilePhotoReviewModal"', false)
            ->assertSee('data-bs-target="#profilePhotoReviewModal"', false);
    }

    public function test_pending_review_filter_orders_oldest_uploads_first(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'aging-admin@example.com']);
        $course = $this->createCourse(['title' => 'Aging Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $newer = $this->createUser([
            'email' => 'aging-newer@example.com',
            'first_name' => 'Newer',
            'profile_photo' => 'profile_photos/newer.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
            'profile_photo_uploaded_at' => now()->subHours(2),
        ]);
        $older = $this->createUser([
            'email' => 'aging-older@example.com',
            'first_name' => 'Older',
            'profile_photo' => 'profile_photos/older.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
            'profile_photo_uploaded_at' => now()->subDays(2),
        ]);
        $this->assignCourseRole($newer, $course, $studentRole);
        $this->assignCourseRole($older, $course, $studentRole);

        $ordered = app(\App\Services\ProfilePhotoAdminService::class)
            ->studentReport('pending_review')
            ->pluck('email')
            ->all();

        $this->assertSame(
            ['aging-older@example.com', 'aging-newer@example.com'],
            array_values(array_intersect($ordered, ['aging-older@example.com', 'aging-newer@example.com']))
        );

        $label = app(\App\Services\ProfilePhotoAdminService::class)->pendingWaitLabel($older);
        $this->assertNotNull($label);
        $this->assertStringContainsString('2', $label);

        $this->actingAs($admin)
            ->get(route('admin.profile-photos.index', ['filter' => 'pending_review']))
            ->assertOk()
            ->assertSee(__('profile_photos.waiting_days', ['count' => 2]), false);
    }

    public function test_admin_can_bulk_approve_pending_photos(): void
    {
        Mail::fake();

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'bulk-admin@example.com']);
        $course = $this->createCourse(['title' => 'Bulk Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $a = $this->createUser([
            'email' => 'bulk-a@example.com',
            'profile_photo' => 'profile_photos/a.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
        ]);
        $b = $this->createUser([
            'email' => 'bulk-b@example.com',
            'profile_photo' => 'profile_photos/b.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
        ]);
        $approved = $this->createUser([
            'email' => 'bulk-already@example.com',
            'profile_photo' => 'profile_photos/ok.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_APPROVED,
        ]);
        $this->assignCourseRole($a, $course, $studentRole);
        $this->assignCourseRole($b, $course, $studentRole);
        $this->assignCourseRole($approved, $course, $studentRole);

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.bulk-approve'), [
                'user_ids' => [$a->user_id, $b->user_id, $approved->user_id],
            ])
            ->assertRedirect();

        $this->assertTrue($a->fresh()->isProfilePhotoApproved());
        $this->assertTrue($b->fresh()->isProfilePhotoApproved());
        $this->assertTrue($approved->fresh()->isProfilePhotoApproved());

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'profile_photo.bulk_approved',
        ]);
    }

    public function test_approve_redirects_to_next_pending_focus(): void
    {
        Mail::fake();

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'focus-admin@example.com']);
        $course = $this->createCourse(['title' => 'Focus Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $first = $this->createUser([
            'email' => 'focus-first@example.com',
            'profile_photo' => 'profile_photos/first.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
            'profile_photo_uploaded_at' => now()->subDays(2),
        ]);
        $second = $this->createUser([
            'email' => 'focus-second@example.com',
            'profile_photo' => 'profile_photos/second.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
            'profile_photo_uploaded_at' => now()->subHour(),
        ]);
        $this->assignCourseRole($first, $course, $studentRole);
        $this->assignCourseRole($second, $course, $studentRole);

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.approve', $first))
            ->assertRedirect(route('admin.profile-photos.index', [
                'filter' => 'pending_review',
                'focus' => $second->user_id,
            ]));
    }

    public function test_admin_report_tolerates_legacy_zero_dates(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'zero-date-admin@example.com']);
        $student = $this->createUser([
            'email' => 'zero-date-student@example.com',
            'profile_photo' => '',
        ]);
        $course = $this->createCourse(['title' => 'Zero Date Course']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($student, $course, $studentRole);

        \Illuminate\Support\Facades\DB::table('user')
            ->where('user_id', $student->user_id)
            ->update([
                'profile_photo_grace_started_at' => '0000-00-00 00:00:00',
                'profile_photo_uploaded_at' => '0000-00-00 00:00:00',
            ]);

        $this->actingAs($admin)
            ->get(route('admin.profile-photos.index'))
            ->assertOk()
            ->assertSee($student->email);
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
                'profile_photo_reupload_reminder_days' => 2,
                'profile_photo_gate_enabled' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse(PortalSettings::current()->fresh()->profile_photo_gate_enabled);
    }

    public function test_admin_can_save_reupload_reminder_days(): void
    {
        $adminRole = $this->createRole('admin');
        $admin = $this->createUser(['email' => 'reminder-settings-admin@example.com']);
        $course = $this->createCourse(['title' => 'Reminder Settings Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $this->actingAs($admin)
            ->put(route('admin.profile-photos.settings'), [
                'profile_photo_grace_days' => 4,
                'profile_photo_reupload_reminder_days' => 5,
                'profile_photo_gate_enabled' => '1',
            ])
            ->assertRedirect();

        $settings = PortalSettings::current()->fresh();
        $this->assertSame(4, (int) $settings->profile_photo_grace_days);
        $this->assertSame(5, (int) $settings->profile_photo_reupload_reminder_days);
        $this->assertTrue((bool) $settings->profile_photo_gate_enabled);
    }

    public function test_reupload_reminder_fires_once_for_eligible_rejected_student(): void
    {
        Mail::fake();

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'reupload-eligible@example.com',
            'profile_photo' => '',
            'profile_photo_status' => User::PHOTO_STATUS_REJECTED,
            'profile_photo_reviewed_at' => now()->subDays(3),
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Reupload Reminder Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        PortalSettings::current()->update(['profile_photo_reupload_reminder_days' => 2]);

        $service = app(\App\Services\ProfilePhotoReuploadReminderService::class);
        $this->assertSame(1, $service->sendReminders());
        $this->assertSame(0, $service->sendReminders());

        $notification = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'profile_photo_reupload_reminder')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame(
            1,
            UserNotification::query()
                ->where('user_id', $student->user_id)
                ->where('type', 'profile_photo_reupload_reminder')
                ->count()
        );
        $this->assertStringContainsString(
            (string) $student->fresh()->profile_photo_reviewed_at->getTimestamp(),
            (string) $notification->dedupe_key
        );

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($student) {
            return $mail->hasTo($student->email);
        });
    }

    public function test_reupload_reminder_skips_student_who_reuploaded_to_pending(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'reupload-pending@example.com',
            'profile_photo' => 'profile_photos/new.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
            'profile_photo_uploaded_at' => now(),
            'profile_photo_reviewed_at' => now()->subDays(5),
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Reupload Pending Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        PortalSettings::current()->update(['profile_photo_reupload_reminder_days' => 2]);

        $sent = app(\App\Services\ProfilePhotoReuploadReminderService::class)->sendReminders();
        $this->assertSame(0, $sent);
        $this->assertSame(
            0,
            UserNotification::query()
                ->where('user_id', $student->user_id)
                ->where('type', 'profile_photo_reupload_reminder')
                ->count()
        );
    }

    public function test_registration_photo_sets_pending_review_status(): void
    {
        $student = $this->createUser([
            'email' => 'registered-photo@example.com',
            'profile_photo' => 'profile_photos/register.jpg',
            'profile_photo_uploaded_at' => now(),
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
        ]);

        $this->assertTrue($student->isProfilePhotoPending());
        $this->assertSame('pending_review', app(ProfilePhotoGateService::class)->reportStatus($student));
    }

    public function test_legacy_registration_photo_without_status_is_pending_review(): void
    {
        $student = $this->createUser([
            'email' => 'legacy-photo@example.com',
            'profile_photo' => 'profile_photos/legacy.jpg',
            'profile_photo_status' => null,
        ]);

        $this->assertTrue($student->isProfilePhotoPending());
        $this->assertTrue($student->needsProfilePhotoReview());
        $this->assertSame('pending_review', app(ProfilePhotoGateService::class)->reportStatus($student));
    }

    public function test_admin_reject_resets_grace_and_sends_email(): void
    {
        Mail::fake();

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');

        $admin = $this->createUser(['email' => 'reject-admin@example.com']);
        $student = $this->createUser([
            'email' => 'reject-student@example.com',
            'profile_photo' => 'profile_photos/reject-me.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
            'profile_photo_grace_started_at' => now()->subDay(),
            'profile_photo_deadline_at' => now()->addDay(),
        ]);
        $course = $this->createCourse(['title' => 'Reject Course']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.reject', $student), [
                'profile_photo_rejection_note' => 'Not a personal photo',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertTrue($student->isProfilePhotoRejected());
        $this->assertSame('', $student->profile_photo);
        $this->assertNull($student->profile_photo_grace_started_at);
        $this->assertNull($student->profile_photo_deadline_at);
        $this->assertSame('Not a personal photo', $student->profile_photo_rejection_note);

        Mail::assertSent(\App\Mail\ProfilePhotoRejectedMail::class, function ($mail) use ($student) {
            return $mail->hasTo($student->email);
        });

        $audit = ActivityLog::query()->where('route_name', 'profile_photo.rejected')->latest('activity_log_id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->user_id, $audit->request_input['actor_user_id'] ?? null);
        $this->assertTrue((bool) ($audit->request_input['has_note'] ?? false));
    }

    public function test_rejected_photo_does_not_show_admin_review_actions(): void
    {
        $student = $this->createUser([
            'email' => 'rejected-stale-photo@example.com',
            'profile_photo' => 'profile_photos/stale-after-reject.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_REJECTED,
        ]);

        $this->assertFalse($student->isProfilePhotoPending());
        $this->assertFalse($student->needsProfilePhotoReview());
    }

    public function test_admin_actions_match_compliance_status(): void
    {
        $gate = app(ProfilePhotoGateService::class);

        $approved = $this->createUser([
            'email' => 'actions-approved@example.com',
            'profile_photo' => 'profile_photos/ok.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_APPROVED,
        ]);
        $this->assertSame(
            ['approve_reject' => false, 'extend_deadline' => false, 'reset_grace' => false, 'revoke' => true],
            $gate->adminActions($approved)
        );

        $pending = $this->createUser([
            'email' => 'actions-pending@example.com',
            'profile_photo' => 'profile_photos/pending.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
        ]);
        $this->assertSame(
            ['approve_reject' => true, 'extend_deadline' => false, 'reset_grace' => false, 'revoke' => false],
            $gate->adminActions($pending)
        );

        $rejected = $this->createUser([
            'email' => 'actions-rejected@example.com',
            'profile_photo' => '',
            'profile_photo_status' => User::PHOTO_STATUS_REJECTED,
            'profile_photo_grace_started_at' => now()->subDay(),
        ]);
        $this->assertSame(
            ['approve_reject' => false, 'extend_deadline' => true, 'reset_grace' => true, 'revoke' => false],
            $gate->adminActions($rejected)
        );

        $notStarted = $this->createUser([
            'email' => 'actions-not-started@example.com',
            'profile_photo' => '',
        ]);
        $this->assertSame(
            ['approve_reject' => false, 'extend_deadline' => true, 'reset_grace' => false, 'revoke' => false],
            $gate->adminActions($notStarted)
        );

        PortalSettings::current()->update([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
        ]);

        $inGrace = $this->createUser([
            'email' => 'actions-in-grace@example.com',
            'profile_photo' => '',
            'profile_photo_grace_started_at' => now()->subDay(),
        ]);
        $this->assertSame(
            ['approve_reject' => false, 'extend_deadline' => true, 'reset_grace' => true, 'revoke' => false],
            $gate->adminActions($inGrace)
        );

        $overdue = $this->createUser([
            'email' => 'actions-overdue@example.com',
            'profile_photo' => '',
            'profile_photo_grace_started_at' => now()->subDays(5),
        ]);
        $this->assertSame(
            ['approve_reject' => false, 'extend_deadline' => true, 'reset_grace' => true, 'revoke' => false],
            $gate->adminActions($overdue)
        );
    }

    public function test_approved_students_do_not_see_extend_or_reset_actions(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'approved-actions-admin@example.com']);
        $student = $this->createUser([
            'email' => 'approved-actions-student@example.com',
            'profile_photo' => 'profile_photos/approved.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_APPROVED,
        ]);
        $course = $this->createCourse(['title' => 'Approved Actions Course']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($admin)
            ->get(route('admin.profile-photos.index', ['filter' => 'approved']))
            ->assertOk()
            ->assertSee(__('profile_photos.revoke'))
            ->assertSee('data-can-extend="0"', false)
            ->assertSee('data-can-reset="0"', false)
            ->assertSee('data-can-revoke="1"', false)
            ->assertDontSee('data-extend-url=', false)
            ->assertDontSee('data-reset-url=', false)
            ->assertSee('data-revoke-url=', false);

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.reset-grace', $student))
            ->assertStatus(422);
    }

    public function test_admin_can_revoke_approved_photo(): void
    {
        Mail::fake();
        Storage::fake('public');

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'revoke-admin@example.com']);
        $student = $this->createUser([
            'email' => 'revoke-student@example.com',
            'profile_photo' => 'profile_photos/revoke-me.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_APPROVED,
            'profile_photo_uploaded_at' => now()->subDay(),
            'profile_photo_grace_started_at' => now()->subDays(5),
            'profile_photo_deadline_at' => now()->addDay(),
        ]);
        $course = $this->createCourse(['title' => 'Revoke Course']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($student, $course, $studentRole);

        Storage::disk('public')->put('profile_photos/revoke-me.jpg', 'fake-image');

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.revoke', $student), [
                'profile_photo_rejection_note' => 'Wrong person in photo',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertTrue($student->isProfilePhotoRejected());
        $this->assertSame('', $student->profile_photo);
        $this->assertNull($student->profile_photo_uploaded_at);
        $this->assertNull($student->profile_photo_grace_started_at);
        $this->assertNull($student->profile_photo_deadline_at);
        $this->assertSame('Wrong person in photo', $student->profile_photo_rejection_note);
        $this->assertSame($admin->user_id, $student->profile_photo_reviewed_by_user_id);
        $this->assertFalse(Storage::disk('public')->exists('profile_photos/revoke-me.jpg'));

        Mail::assertSent(\App\Mail\ProfilePhotoRejectedMail::class, function ($mail) use ($student) {
            return $mail->hasTo($student->email);
        });

        $audit = ActivityLog::query()->where('route_name', 'profile_photo.revoked')->latest('activity_log_id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->user_id, $audit->request_input['actor_user_id'] ?? null);
        $this->assertSame($student->user_id, $audit->request_input['target_user_id'] ?? null);
        $this->assertTrue((bool) ($audit->request_input['has_note'] ?? false));
    }

    public function test_revoke_requires_note_and_approved_status(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'revoke-guard-admin@example.com']);
        $approved = $this->createUser([
            'email' => 'revoke-guard-approved@example.com',
            'profile_photo' => 'profile_photos/ok.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_APPROVED,
        ]);
        $pending = $this->createUser([
            'email' => 'revoke-guard-pending@example.com',
            'profile_photo' => 'profile_photos/pending.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_PENDING,
        ]);
        $course = $this->createCourse(['title' => 'Revoke Guard Course']);
        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($approved, $course, $studentRole);
        $this->assignCourseRole($pending, $course, $studentRole);

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.revoke', $approved), [])
            ->assertSessionHasErrors('profile_photo_rejection_note');

        $this->actingAs($admin)
            ->post(route('admin.profile-photos.revoke', $pending), [
                'profile_photo_rejection_note' => 'Should not work',
            ])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_revoke_approved_photo(): void
    {
        $studentRole = $this->createRole('student');
        $actor = $this->createUser(['email' => 'revoke-forbidden-actor@example.com']);
        $student = $this->createUser([
            'email' => 'revoke-forbidden-student@example.com',
            'profile_photo' => 'profile_photos/approved.jpg',
            'profile_photo_status' => User::PHOTO_STATUS_APPROVED,
        ]);
        $course = $this->createCourse(['title' => 'Revoke Forbidden Course']);
        $this->assignCourseRole($actor, $course, $studentRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($actor)
            ->post(route('admin.profile-photos.revoke', $student), [
                'profile_photo_rejection_note' => 'Must not be allowed',
            ])
            ->assertForbidden();
    }

    public function test_photo_upload_notifies_course_admins_via_portal_and_email(): void
    {
        Mail::fake();
        Storage::fake('public');

        $adminRole = $this->createRole('admin');
        $instructorRole = $this->createRole('instructor');
        $studentRole = $this->createRole('student');

        $adminA = $this->createUser(['email' => 'photo-notify-admin-a@example.com']);
        $adminB = $this->createUser(['email' => 'photo-notify-admin-b@example.com']);
        $instructor = $this->createUser(['email' => 'photo-notify-instructor@example.com']);
        $student = $this->createUser([
            'email' => 'photo-notify-student@example.com',
            'first_name' => 'نور',
            'second_name' => 'مراد',
            'third_name' => 'حبيب',
            'profile_photo' => '',
            'registration_completed' => true,
        ]);

        $course = $this->createCourse(['title' => 'Photo Notify Course']);
        $this->assignCourseRole($adminA, $course, $adminRole);
        $this->assignCourseRole($adminB, $course, $adminRole);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($student)
            ->put(route('profile.picture.update'), [
                'profile_photo' => UploadedFile::fake()->image('urgent.jpg', 400, 400),
            ])
            ->assertRedirect(route('profile'));

        $student->refresh();
        $this->assertTrue($student->isProfilePhotoPending());

        foreach ([$adminA, $adminB] as $admin) {
            $notification = UserNotification::query()
                ->where('user_id', $admin->user_id)
                ->where('type', 'profile_photo_pending_review')
                ->first();

            $this->assertNotNull($notification, "Expected portal notification for {$admin->email}");
            $this->assertSame(UserNotification::PRIORITY_HIGH, $notification->priority);
            $this->assertStringContainsString('filter=pending_review', (string) $notification->action_url);
            $this->assertStringContainsString($student->displayName(), $notification->body);
        }

        $this->assertSame(
            0,
            UserNotification::query()
                ->where('user_id', $instructor->user_id)
                ->where('type', 'profile_photo_pending_review')
                ->count()
        );

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($adminA) {
            return $mail->hasTo($adminA->email);
        });
        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($adminB) {
            return $mail->hasTo($adminB->email);
        });
        Mail::assertNotSent(NotificationMail::class, function (NotificationMail $mail) use ($instructor) {
            return $mail->hasTo($instructor->email);
        });
    }
}

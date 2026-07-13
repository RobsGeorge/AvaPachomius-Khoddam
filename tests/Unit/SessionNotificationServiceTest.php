<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Module;
use App\Models\Session;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\SessionNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EventModuleTestCase;

class SessionNotificationServiceTest extends EventModuleTestCase
{
    use RefreshDatabase;

    private function attachModule(Course $course): Module
    {
        $module = Module::create(['title' => 'Module 1', 'description' => 'Test']);
        DB::table('course_module')->insert([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
        ]);

        return $module;
    }

    /** @return array{course: Course, session: Session, students: array<int, User>} */
    private function seedFutureSession(int $studentCount = 2, bool $notifyStudents = true): array
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $module = $this->attachModule($course);

        $session = Session::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'session_title' => 'Upcoming Lecture',
            'session_date' => now()->addDay()->toDateString(),
            'session_start_time' => '10:00:00',
            'notify_students' => $notifyStudents,
        ]);

        $students = [];
        for ($i = 0; $i < $studentCount; $i++) {
            $student = $this->createUser(['email' => "session-student-{$i}@example.com"]);
            $this->assignCourseRole($student, $course, $roles['student']);
            $students[] = $student;
        }

        return compact('course', 'session', 'students');
    }

    public function test_resolve_recipients_returns_all_enrolled_when_no_targets(): void
    {
        ['session' => $session, 'students' => $students] = $this->seedFutureSession(2);

        $recipients = app(SessionNotificationService::class)->resolveRecipients($session);

        $this->assertCount(2, $recipients);
        $this->assertTrue($recipients->pluck('user_id')->contains($students[0]->user_id));
        $this->assertTrue($recipients->pluck('user_id')->contains($students[1]->user_id));
    }

    public function test_resolve_recipients_returns_empty_when_notify_students_disabled(): void
    {
        ['session' => $session] = $this->seedFutureSession(2, false);

        $recipients = app(SessionNotificationService::class)->resolveRecipients($session);

        $this->assertCount(0, $recipients);
    }

    public function test_resolve_recipients_honors_subset_targets(): void
    {
        ['session' => $session, 'students' => $students] = $this->seedFutureSession(3);

        DB::table('session_notification_targets')->insert([
            'session_id' => $session->session_id,
            'user_id' => $students[0]->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $session->load('notificationTargets');

        $recipients = app(SessionNotificationService::class)->resolveRecipients($session);

        $this->assertCount(1, $recipients);
        $this->assertSame($students[0]->user_id, $recipients->first()->user_id);
    }

    public function test_resolve_recipients_excludes_targets_not_enrolled(): void
    {
        ['session' => $session, 'students' => $students] = $this->seedFutureSession(1);
        $outsider = $this->createUser(['email' => 'outsider@example.com']);

        DB::table('session_notification_targets')->insert([
            ['session_id' => $session->session_id, 'user_id' => $students[0]->user_id, 'created_at' => now(), 'updated_at' => now()],
            ['session_id' => $session->session_id, 'user_id' => $outsider->user_id, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $session->load('notificationTargets');

        $recipients = app(SessionNotificationService::class)->resolveRecipients($session);

        $this->assertCount(1, $recipients);
        $this->assertSame($students[0]->user_id, $recipients->first()->user_id);
    }

    public function test_is_future_session_false_for_past_session(): void
    {
        ['session' => $session] = $this->seedFutureSession(0);
        $session->update(['session_date' => now()->subDay()->toDateString()]);

        $this->assertFalse(app(SessionNotificationService::class)->isFutureSession($session->fresh()));
    }

    public function test_next_notifiable_session_skips_disabled_sessions(): void
    {
        ['course' => $course, 'session' => $disabled] = $this->seedFutureSession(0, false);
        $module = $this->attachModule($course);

        $enabled = Session::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'session_title' => 'Later Session',
            'session_date' => now()->addDays(2)->toDateString(),
            'notify_students' => true,
        ]);

        $disabled->update(['session_date' => now()->addDay()->toDateString()]);

        $next = app(SessionNotificationService::class)->nextNotifiableSession($course);

        $this->assertNotNull($next);
        $this->assertSame($enabled->session_id, $next->session_id);
    }

    public function test_sync_targets_filters_to_enrolled_students_only(): void
    {
        ['session' => $session, 'students' => $students] = $this->seedFutureSession(1);
        $outsider = $this->createUser(['email' => 'sync-outsider@example.com']);

        app(SessionNotificationService::class)->syncTargets($session, [
            $students[0]->user_id,
            $outsider->user_id,
        ]);

        $this->assertDatabaseCount('session_notification_targets', 1);
        $this->assertDatabaseHas('session_notification_targets', [
            'session_id' => $session->session_id,
            'user_id' => $students[0]->user_id,
        ]);
    }
}

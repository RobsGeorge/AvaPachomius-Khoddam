<?php

namespace Tests\Feature;

use App\Mail\DailyBirthdayAnnouncementMail;
use App\Mail\MonthlyBirthdayAnnouncementMail;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class StudentBirthdayAnnouncementTest extends EventModuleTestCase
{
    public function test_admin_can_send_birthday_announcement_for_course(): void
    {
        Mail::fake();

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');

        $admin = $this->createUser(['email' => 'birthday-admin@example.com']);
        $instructor = $this->createUser(['email' => 'birthday-instructor@example.com']);
        $course = $this->createCourse(['title' => 'Birthday Course']);

        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $birthdayMonth = now(config('attendance.timezone', config('app.timezone')))->month;

        $student = $this->createUser([
            'email' => 'birthday-student@example.com',
            'date_of_birth' => sprintf('2000-%02d-15', $birthdayMonth),
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($admin)
            ->post(route('students.roster.announce', $course))
            ->assertRedirect(route('students.roster', ['course' => $course->course_id]))
            ->assertSessionHas('success');

        Mail::assertSent(MonthlyBirthdayAnnouncementMail::class, 2);
    }

    public function test_instructor_can_open_birthdays_page_and_sees_dashboard_tile(): void
    {
        $instructorRole = $this->courseRoleWithPermissions(
            $this->createCourse(['title' => 'Birthdays Staff Course']),
            'instructor',
            ['roster.view', 'roster.announce', 'course.access']
        );
        $course = $instructorRole->course;
        $studentRole = $this->courseRoleWithPermissions($course, 'student', ['roster.view', 'course.access']);

        $instructor = $this->createUser(['email' => 'bday-instructor-page@example.com']);
        $student = $this->createUser([
            'email' => 'bday-peer@example.com',
            'date_of_birth' => now(config('attendance.timezone', config('app.timezone')))->format('Y-m-d'),
        ]);

        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->actingAs($instructor)
            ->get(route('students.birthdays'))
            ->assertOk()
            ->assertSee(__('students.birthdays_title'), false);

        $this->actingAs($instructor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('students.birthdays'), false)
            ->assertSee(__('dashboard.birthdays'), false);
    }

    public function test_announcement_warns_when_no_birthdays_this_month(): void
    {
        Mail::fake();

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');

        $admin = $this->createUser(['email' => 'no-birthday-admin@example.com']);
        $course = $this->createCourse(['title' => 'Quiet Course']);

        $this->assignCourseRole($admin, $course, $adminRole);

        $student = $this->createUser([
            'email' => 'no-birthday-student@example.com',
            'date_of_birth' => '2000-01-15',
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        if (now()->month === 1) {
            $student->update(['date_of_birth' => '2000-06-15']);
        }

        $this->actingAs($admin)
            ->post(route('students.roster.announce', $course))
            ->assertRedirect(route('students.roster', ['course' => $course->course_id]))
            ->assertSessionHas('warning');

        Mail::assertNothingSent();
    }

    public function test_birthday_announcement_mail_renders_without_error(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');

        $admin = $this->createUser(['email' => 'render-admin@example.com']);
        $course = $this->createCourse(['title' => 'Render Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $birthdayMonth = now(config('attendance.timezone', config('app.timezone')))->month;
        $student = $this->createUser([
            'email' => 'render-student@example.com',
            'date_of_birth' => sprintf('2000-%02d-15', $birthdayMonth),
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        $rosterService = app(\App\Services\StudentRosterService::class);
        $students = $rosterService->enrolledStudents($course);
        $birthdayStudents = $rosterService->studentsWithBirthdayInMonth($students, $birthdayMonth);

        $mailable = new MonthlyBirthdayAnnouncementMail(
            $course,
            $birthdayStudents,
            $birthdayMonth,
            (int) now()->year,
            $admin
        );

        $html = $mailable->render();

        $this->assertStringContainsString($student->displayName(), $html);
        $this->assertStringContainsString($course->title, $html);
    }

    public function test_get_request_to_announce_route_is_not_allowed(): void
    {
        $adminRole = $this->createRole('admin');
        $admin = $this->createUser(['email' => 'get-admin@example.com']);
        $course = $this->createCourse(['title' => 'GET Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $this->actingAs($admin)
            ->get('/courses/'.$course->course_id.'/students/birthday-announcement')
            ->assertStatus(405);
    }

    public function test_daily_birthday_command_emails_staff_and_creates_portal_notifications(): void
    {
        Mail::fake();

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');

        $admin = $this->createUser(['email' => 'daily-birthday-admin@example.com']);
        $instructor = $this->createUser(['email' => 'daily-birthday-instructor@example.com']);
        $course = $this->createCourse(['title' => 'Daily Birthday Course']);

        $this->assignCourseRole($admin, $course, $adminRole);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $today = now(config('attendance.timezone', config('app.timezone')));

        $student = $this->createUser([
            'email' => 'daily-birthday-student@example.com',
            'date_of_birth' => sprintf('2000-%02d-%02d', $today->month, $today->day),
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->artisan('birthdays:notify-daily', ['--date' => $today->toDateString()])
            ->assertSuccessful();

        Mail::assertSent(DailyBirthdayAnnouncementMail::class, 2);

        $this->assertSame(
            2,
            UserNotification::query()
                ->where('type', 'birthday_today')
                ->where('dedupe_key', 'birthday_today:'.$course->course_id.':'.$today->format('Y-m-d'))
                ->count()
        );
    }

    public function test_daily_birthday_command_skips_when_no_birthdays_today(): void
    {
        Mail::fake();

        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');

        $admin = $this->createUser(['email' => 'daily-quiet-admin@example.com']);
        $course = $this->createCourse(['title' => 'Quiet Daily Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $student = $this->createUser([
            'email' => 'daily-quiet-student@example.com',
            'date_of_birth' => '2000-01-15',
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        $this->artisan('birthdays:notify-daily', ['--date' => '2026-06-01'])
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, UserNotification::query()->where('type', 'birthday_today')->count());
    }

    public function test_daily_birthday_announcement_mail_renders_without_error(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');

        $admin = $this->createUser(['email' => 'daily-render-admin@example.com']);
        $course = $this->createCourse(['title' => 'Daily Render Course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $today = now(config('attendance.timezone', config('app.timezone')));

        $student = $this->createUser([
            'email' => 'daily-render-student@example.com',
            'date_of_birth' => sprintf('2000-%02d-%02d', $today->month, $today->day),
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        $rosterService = app(\App\Services\StudentRosterService::class);
        $students = $rosterService->enrolledStudents($course);
        $birthdayStudents = $rosterService->studentsWithBirthdayToday($students, $today);

        $mailable = new DailyBirthdayAnnouncementMail(
            $course,
            $birthdayStudents,
            $today,
            $admin
        );

        $html = $mailable->render();

        $this->assertStringContainsString($student->displayName(), $html);
        $this->assertStringContainsString($course->title, $html);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseApplication;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\Lecture;
use App\Models\Module;
use App\Models\Session;
use App\Models\StudentGrade;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\BirthdayNotificationService;
use App\Services\CourseApplicationFormService;
use App\Services\CourseApplicationService;
use App\Services\NotificationScannerService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class NotificationActionLinkTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function clickNotification(User $user, UserNotification $notification)
    {
        return $this->actingAs($user)
            ->followingRedirects()
            ->get(route('notifications.show', $notification));
    }

    public function test_admin_announcement_link_opens_announcement_for_student(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser(['email' => 'notif-link-announce@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $studentRole);

        $announcement = Announcement::create([
            'created_by_user_id' => $student->user_id,
            'course_id' => $course->course_id,
            'title' => 'Portal announcement',
            'body' => 'Read this update.',
            'target_mode' => Announcement::TARGET_COURSE,
            'channels' => [Announcement::CHANNEL_HOMEPAGE => true],
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        AnnouncementDelivery::create([
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $student->user_id,
        ]);

        $notification = UserNotification::create([
            'user_id' => $student->user_id,
            'type' => 'admin_announcement',
            'title' => $announcement->title,
            'body' => 'Preview',
            'action_url' => route('announcements.show', $announcement),
            'dedupe_key' => "admin_announcement:{$announcement->announcement_id}",
        ]);

        $this->clickNotification($student, $notification)
            ->assertOk()
            ->assertSee('Read this update.');
    }

    public function test_assignment_deadline_link_opens_assignment_for_student(): void
    {
        $roles = $this->seedBasicRoles();
        $student = $this->createUser(['email' => 'notif-link-assignment@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $roles['student']);

        $assignment = Assignment::create([
            'assignment_name' => 'Linked assignment',
            'assignment_description' => 'Submit soon',
            'total_points' => 10,
            'due_date' => now()->addDay(),
        ]);

        $notification = UserNotification::create([
            'user_id' => $student->user_id,
            'type' => 'assignment_deadline',
            'title' => 'Due soon',
            'body' => 'Tomorrow',
            'action_url' => route('assignments.show', $assignment),
            'dedupe_key' => "assignment_deadline:{$assignment->assignment_id}:test",
        ]);

        $this->clickNotification($student, $notification)
            ->assertOk()
            ->assertSee('Linked assignment');
    }

    public function test_grade_posted_link_opens_course_grades_for_student(): void
    {
        $roles = $this->seedBasicRoles();
        $student = $this->createUser(['email' => 'notif-link-grade@example.com']);
        $course = $this->createCourse(['title' => 'Grade Link Course']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $category = GradeCategory::create([
            'course_id' => $course->course_id,
            'type' => 'exam',
            'name' => 'Homework',
            'weight_percentage' => 100,
            'ordering' => 0,
        ]);
        $item = GradeItem::create([
            'category_id' => $category->category_id,
            'title' => 'Quiz 1',
            'max_score' => 10,
            'ordering' => 0,
        ]);
        $grade = StudentGrade::create([
            'item_id' => $item->item_id,
            'user_id' => $student->user_id,
            'score' => 8,
        ]);

        app(NotificationScannerService::class)->notifyGradePosted($grade);

        $notification = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'grade_posted')
            ->firstOrFail();

        $this->clickNotification($student, $notification)
            ->assertOk()
            ->assertSee('Quiz 1');
    }

    public function test_grade_posted_without_course_has_no_action_url(): void
    {
        $student = $this->createUser(['email' => 'notif-link-grade-missing@example.com']);

        app(\App\Services\NotificationGeneratorService::class)->createOrUpdate(
            $student,
            'grade_posted',
            'Detached grade',
            'Score posted',
            null,
            'student_grade',
            99,
            UserNotification::PRIORITY_NORMAL,
            [],
            'grade_posted:orphan:99'
        );

        $notification = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'grade_posted')
            ->firstOrFail();

        $this->assertNull($notification->action_url);
    }

    public function test_new_lecture_content_link_opens_curriculum_for_student(): void
    {
        $roles = $this->seedBasicRoles();
        $student = $this->createUser(['email' => 'notif-link-lecture@example.com']);
        $course = $this->createCourse(['title' => 'Lecture Link Course']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $module = Module::create(['title' => 'Week 1', 'description' => 'Intro']);
        $course->modules()->attach($module->module_id);
        $lecture = Lecture::create([
            'module_id' => $module->module_id,
            'title' => 'Opening lecture',
            'notes' => 'Welcome',
            'week_number' => 1,
        ]);

        app(NotificationScannerService::class)->notifyNewLecture($lecture);

        $notification = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'new_lecture_content')
            ->firstOrFail();

        $this->clickNotification($student, $notification)
            ->assertOk()
            ->assertSee('Opening lecture');
    }

    public function test_event_nearby_link_opens_event_page_for_student(): void
    {
        $roles = $this->seedBasicRoles();
        $student = $this->createUser(['email' => 'notif-link-event@example.com']);
        $course = $this->createCourse();
        $this->assignCourseRole($student, $course, $roles['student']);

        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'notif-link-event-admin@example.com']);
        $event = $this->createEvent($admin, [
            'title' => 'Nearby retreat',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'course_id' => $course->course_id,
        ]);

        app(NotificationScannerService::class)->notifyEventPublished($event);

        $notification = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'event_new_reservable')
            ->firstOrFail();

        $this->clickNotification($student, $notification)
            ->assertOk()
            ->assertSee('Nearby retreat');
    }

    public function test_event_reservation_cancelled_only_notifies_event_admins_and_link_works(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $student = $this->createUser(['email' => 'notif-link-cancel-student@example.com']);
        $instructor = $this->createUser(['email' => 'notif-link-cancel-instructor@example.com']);
        $eventAdmin = $this->createUser(['email' => 'notif-link-cancel-admin@example.com']);

        $instructorRole = $this->createRole('instructor');
        $this->assignCourseRole($student, $course, $roles['student']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->makeEventAdmin($eventAdmin);

        $event = $this->createEvent($eventAdmin, [
            'title' => 'Cancel notify event',
            'course_id' => $course->course_id,
        ]);

        $reservation = EventReservation::create([
            'event_id' => $event->event_id,
            'user_id' => $student->user_id,
            'status' => EventReservation::STATUS_CANCELLED,
            'reserved_at' => now(),
        ]);

        app(NotificationScannerService::class)->notifyReservationCancelled($reservation);

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $instructor->user_id)
                ->where('type', 'event_reservation_cancelled')
                ->exists()
        );

        $notification = UserNotification::query()
            ->where('user_id', $eventAdmin->user_id)
            ->where('type', 'event_reservation_cancelled')
            ->firstOrFail();

        $this->clickNotification($eventAdmin, $notification)
            ->assertOk()
            ->assertSee('Cancel notify event');
    }

    public function test_attendance_absent_streak_link_opens_user_attendance_report(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse();
        $student = $this->createUser(['email' => 'notif-link-absent-student@example.com']);
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'notif-link-absent-admin@example.com']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $notification = UserNotification::create([
            'user_id' => $admin->user_id,
            'type' => 'attendance_absent_streak',
            'title' => 'Absent streak',
            'body' => 'Check attendance',
            'action_url' => route('attendance.user-report', $student->user_id),
            'dedupe_key' => "attendance_absent_streak:{$student->user_id}:{$course->course_id}",
        ]);

        $this->clickNotification($admin, $notification)
            ->assertOk()
            ->assertSee($student->first_name);
    }

    public function test_assignment_needs_grading_link_opens_status_report_for_staff(): void
    {
        $roles = $this->seedBasicRoles();
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'notif-link-grading@example.com']);
        $assignment = Assignment::create([
            'assignment_name' => 'Needs grading',
            'assignment_description' => 'Grade me',
            'total_points' => 10,
            'due_date' => now()->addDay(),
        ]);

        $notification = UserNotification::create([
            'user_id' => $admin->user_id,
            'type' => 'assignment_needs_grading',
            'title' => 'Grade submissions',
            'body' => 'Pending work',
            'action_url' => route('assignments.status', $assignment),
            'dedupe_key' => "assignment_needs_grading:{$assignment->assignment_id}",
        ]);

        $this->clickNotification($admin, $notification)
            ->assertOk()
            ->assertSee('Needs grading');
    }

    public function test_below_passing_grade_link_opens_graduation_report_for_staff(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse([
            'title' => 'Below passing course',
            'passing_percentage' => 60,
            'min_attendance_percentage' => 75,
        ]);
        $student = $this->createUser(['email' => 'notif-link-below-student@example.com']);
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'notif-link-below-admin@example.com']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $notification = UserNotification::create([
            'user_id' => $admin->user_id,
            'type' => 'below_passing_grade',
            'title' => 'Below passing',
            'body' => 'Review grades',
            'action_url' => route('graduation.show', $course),
            'dedupe_key' => "below_passing_grade:{$course->course_id}:{$student->user_id}",
        ]);

        $this->clickNotification($admin, $notification)
            ->assertOk()
            ->assertSee('Below passing course');
    }

    public function test_birthday_today_link_opens_roster_for_staff(): void
    {
        $adminRole = $this->createRole('admin');
        $studentRole = $this->createRole('student');
        $admin = $this->createUser(['email' => 'notif-link-birthday-admin@example.com']);
        $course = $this->createCourse(['title' => 'Birthday roster course']);
        $this->assignCourseRole($admin, $course, $adminRole);

        $today = now(config('attendance.timezone', config('app.timezone')));
        $student = $this->createUser([
            'email' => 'notif-link-birthday-student@example.com',
            'date_of_birth' => sprintf('2000-%02d-%02d', $today->month, $today->day),
        ]);
        $this->assignCourseRole($student, $course, $studentRole);

        app(BirthdayNotificationService::class)->notifyStaffForCourseToday($course, $today);

        $notification = UserNotification::query()
            ->where('user_id', $admin->user_id)
            ->where('type', 'birthday_today')
            ->firstOrFail();

        $this->assertSame(route('students.roster'), $notification->action_url);

        $this->clickNotification($admin, $notification)
            ->assertOk();
    }

    public function test_course_application_submitted_skips_instructors_without_admin_access(): void
    {
        $roles = $this->seedBasicRoles();
        $instructorRole = $this->createRole('instructor');
        $course = $this->createCourse(['title' => 'Application notify course']);
        $instructor = $this->createUser(['email' => 'notif-link-app-instructor@example.com']);
        $admin = $this->createUser(['email' => 'notif-link-app-admin@example.com']);
        $student = $this->createUser(['email' => 'notif-link-app-student@example.com']);

        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($admin, $course, $roles['admin']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $form = app(CourseApplicationFormService::class)->getOrCreateForCourse($course);
        $application = CourseApplication::create([
            'user_id' => $student->user_id,
            'course_id' => $course->course_id,
            'form_id' => $form->id,
            'status' => CourseApplication::STATUS_PENDING_REVIEW,
            'snapshot' => ['motivation' => 'Join please'],
            'submitted_at' => now(),
        ]);

        $reflection = new \ReflectionClass(CourseApplicationService::class);
        $method = $reflection->getMethod('notifyStaffOfSubmission');
        $method->setAccessible(true);
        $method->invoke(app(CourseApplicationService::class), $application);

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $instructor->user_id)
                ->where('type', 'course_application_submitted')
                ->exists()
        );

        $notification = UserNotification::query()
            ->where('user_id', $admin->user_id)
            ->where('type', 'course_application_submitted')
            ->firstOrFail();

        $this->clickNotification($admin, $notification)
            ->assertOk()
            ->assertSee('Application notify course');
    }

    public function test_course_graduation_announced_link_opens_graduation_report(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse([
            'title' => 'Announced graduation course',
            'passing_percentage' => 60,
            'min_attendance_percentage' => 75,
        ]);
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'notif-link-graduation@example.com']);
        $student = $this->createUser(['email' => 'notif-link-graduation-student@example.com']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $notification = UserNotification::create([
            'user_id' => $admin->user_id,
            'type' => 'course_graduation_announced',
            'title' => 'Grades announced',
            'body' => 'Review graduation report',
            'action_url' => route('graduation.show', $course),
            'dedupe_key' => "course_graduation_announced:test:{$admin->user_id}",
        ]);

        $this->clickNotification($admin, $notification)
            ->assertOk()
            ->assertSee('Announced graduation course');
    }

    public function test_exam_upcoming_link_opens_exam_lobby_for_student(): void
    {
        $roles = $this->seedBasicRoles();
        $course = $this->createCourse(['title' => 'Exam link course']);
        $student = $this->createUser(['email' => 'notif-link-exam-student@example.com']);
        $this->assignCourseRole($student, $course, $roles['student']);

        $exam = Exam::create([
            'course_id' => $course->course_id,
            'exam_name' => 'Midterm exam',
            'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_ONLINE,
            'duration_minutes' => 60,
            'passing_score' => 50,
            'is_published' => true,
            'total_points' => 100,
        ]);
        $schedule = ExamSchedule::create([
            'exam_id' => $exam->exam_id,
            'scheduled_date' => now()->addHours(6),
            'is_completed' => false,
        ]);

        $notification = UserNotification::create([
            'user_id' => $student->user_id,
            'type' => 'exam_upcoming',
            'title' => 'Exam soon',
            'body' => 'Prepare',
            'action_url' => route('exams.attempt.lobby', $schedule),
            'dedupe_key' => "exam_upcoming:{$schedule->schedule_id}:user:{$student->user_id}",
        ]);

        $this->clickNotification($student, $notification)
            ->assertOk()
            ->assertSee('Midterm exam');
    }
}

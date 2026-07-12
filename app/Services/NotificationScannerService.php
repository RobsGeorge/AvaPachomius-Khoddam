<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Event;
use App\Models\ExamSchedule;
use App\Models\GradeItem;
use App\Models\Lecture;
use App\Models\Session;
use App\Models\StudentGrade;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserNotification;
use App\Models\Role;
use App\Models\EventReservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationScannerService
{
    public function __construct(
        private NotificationGeneratorService $generator,
        private NotificationPreferenceService $preferences,
        private StudentRosterService $roster,
        private GraduationService $graduation,
        private AttendanceCloseService $attendanceClose
    ) {}

    public function scanDeadlines(): int
    {
        $count = 0;
        $count += $this->scanAssignmentDeadlines();
        $count += $this->scanExamUpcoming();

        return $count;
    }

    public function scanEventsAndBirthdays(): int
    {
        return $this->scanNearbyEvents();
    }

    public function scanInstructorAlerts(): int
    {
        $count = 0;
        $count += $this->scanUnclosedSessions();
        $count += $this->scanAbsentStreaks();
        $count += $this->scanUngradedAssignments();

        return $count;
    }

    public function scanBelowPassingGrades(): int
    {
        $count = 0;
        $staffRoleIds = Role::staffRoleIds();

        $courseIds = UserCourseRole::query()
            ->whereIn('role_id', $staffRoleIds)
            ->distinct()
            ->pluck('course_id');

        foreach ($courseIds as $courseId) {
            $course = \App\Models\Course::query()->with(['gradeCategories.items.grades'])->find($courseId);
            if (! $course || ! $course->hasGraduationCriteria()) {
                continue;
            }

            $students = $this->roster->enrolledStudents($course);
            $attendanceMap = $this->graduation->attendancePercentagesForCourse($course);

            foreach ($students as $student) {
                $attendancePct = $attendanceMap[$student->user_id] ?? 0;
                $eval = $this->graduation->evaluateStudent($course, $student, $attendancePct);

                if ($eval['grade_pass']) {
                    continue;
                }

                $staff = $this->roster->courseStaff($courseId);
                foreach ($staff as $instructor) {
                    $this->preferences->ensureDefaults($instructor);
                    $this->generator->createOrUpdate(
                        $instructor,
                        'below_passing_grade',
                        __('notifications.generated.below_passing_title', ['course' => $course->title]),
                        __('notifications.generated.below_passing_body', [
                            'student' => $student->displayName(),
                            'grade' => number_format((float) $eval['total_grade'], 1),
                        ]),
                        route('graduation.show', $course),
                        'course',
                        $course->course_id,
                        UserNotification::PRIORITY_NORMAL,
                        ['student_id' => $student->user_id],
                        "below_passing_grade:course:{$course->course_id}:student:{$student->user_id}"
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    public function notifyGradePosted(StudentGrade $grade): void
    {
        $grade->loadMissing(['item.category.course', 'student']);
        $student = $grade->student;
        if (! $student) {
            return;
        }

        $this->preferences->ensureDefaults($student);
        $course = $grade->item?->category?->course;
        $url = $course ? route('grades.show', $course) : route('notifications.index');

        $this->generator->createOrUpdate(
            $student,
            'grade_posted',
            __('notifications.generated.grade_posted_title', ['item' => $grade->item?->title ?? '']),
            __('notifications.generated.grade_posted_body', [
                'score' => $grade->score ?? '—',
                'max' => $grade->item?->max_score ?? '—',
            ]),
            $url,
            'student_grade',
            $grade->grade_id ?? $grade->id,
            UserNotification::PRIORITY_NORMAL,
            ['course_id' => $course?->course_id],
            "grade_posted:{$grade->getKey()}"
        );
    }

    public function notifyNewLecture(Lecture $lecture): void
    {
        $lecture->loadMissing(['module.courses']);
        $courseIds = $lecture->module?->courses?->pluck('course_id') ?? collect();

        foreach ($courseIds as $courseId) {
            $students = $this->roster->enrolledStudents(\App\Models\Course::find($courseId));
            foreach ($students as $student) {
                $this->preferences->ensureDefaults($student);
                $this->generator->createOrUpdate(
                    $student,
                    'new_lecture_content',
                    __('notifications.generated.new_lecture_title', ['title' => $lecture->title]),
                    __('notifications.generated.new_lecture_body', ['course' => $lecture->module?->courses?->first()?->title ?? '']),
                    route('curriculum.show', $courseId),
                    'lecture',
                    $lecture->lecture_id,
                    UserNotification::PRIORITY_NORMAL,
                    ['course_id' => $courseId],
                    "new_lecture_content:{$lecture->lecture_id}:user:{$student->user_id}"
                );
            }
        }
    }

    public function notifyEventPublished(Event $event): void
    {
        $students = $this->enrolledStudentsForEvent($event);
        foreach ($students as $student) {
            $this->preferences->ensureDefaults($student);
            $this->generator->createOrUpdate(
                $student,
                'event_new_reservable',
                __('notifications.generated.event_new_title', ['title' => $event->title]),
                __('notifications.generated.event_new_body'),
                route('events.show', $event),
                'event',
                $event->event_id,
                UserNotification::PRIORITY_NORMAL,
                [],
                "event_new_reservable:{$event->event_id}:user:{$student->user_id}"
            );
        }
    }

    public function notifyReservationCancelled(EventReservation $reservation): void
    {
        $reservation->loadMissing(['event', 'user']);
        $event = $reservation->event;
        if (! $event) {
            return;
        }

        $staff = $this->eventStaff($event);
        foreach ($staff as $user) {
            $this->preferences->ensureDefaults($user);
            $this->generator->createOrUpdate(
                $user,
                'event_reservation_cancelled',
                __('notifications.generated.event_cancel_title', ['title' => $event->title]),
                __('notifications.generated.event_cancel_body', ['name' => $reservation->user?->displayName() ?? '']),
                route('events.admin.reservations', $event),
                'event_reservation',
                $reservation->reservation_id ?? $reservation->id,
                UserNotification::PRIORITY_NORMAL,
                [],
                "event_reservation_cancelled:{$reservation->getKey()}"
            );
        }
    }

    public function notifyAnnouncement(User $user, Announcement $announcement): void
    {
        $this->preferences->ensureDefaults($user);
        $this->generator->createOrUpdate(
            $user,
            UserNotification::TYPE_ADMIN_ANNOUNCEMENT,
            $announcement->title,
            \Illuminate\Support\Str::limit($announcement->body, 200),
            route('announcements.show', $announcement),
            'announcement',
            $announcement->announcement_id,
            UserNotification::PRIORITY_HIGH,
            [],
            "admin_announcement:{$announcement->announcement_id}"
        );
    }

    public function fireDueReminders(): int
    {
        $count = 0;
        $reminders = \App\Models\UserNotificationReminder::query()
            ->where('remind_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('last_fired_at')
                    ->orWhere('recurrence', '!=', \App\Models\UserNotificationReminder::RECURRENCE_ONCE);
            })
            ->with('user')
            ->get();

        foreach ($reminders as $reminder) {
            $user = $reminder->user;
            if (! $user) {
                continue;
            }

            if ($reminder->recurrence === \App\Models\UserNotificationReminder::RECURRENCE_ONCE
                && $reminder->last_fired_at !== null) {
                continue;
            }

            $occurrenceKey = $reminder->recurrence === \App\Models\UserNotificationReminder::RECURRENCE_ONCE
                ? 'once'
                : $reminder->remind_at->format('Y-m-d-H-i');

            $this->generator->createOrUpdate(
                $user,
                'custom_reminder',
                $reminder->title,
                $reminder->body ?? '',
                route('notifications.index'),
                'user_notification_reminder',
                $reminder->id,
                UserNotification::PRIORITY_NORMAL,
                [],
                'custom_reminder:'.$reminder->id.':'.$occurrenceKey
            );

            $nextFired = now();
            if ($reminder->recurrence === \App\Models\UserNotificationReminder::RECURRENCE_DAILY) {
                $reminder->update([
                    'last_fired_at' => $nextFired,
                    'remind_at' => $reminder->remind_at->addDay(),
                ]);
            } elseif ($reminder->recurrence === \App\Models\UserNotificationReminder::RECURRENCE_WEEKLY) {
                $reminder->update([
                    'last_fired_at' => $nextFired,
                    'remind_at' => $reminder->remind_at->addWeek(),
                ]);
            } else {
                $reminder->update(['last_fired_at' => $nextFired]);
            }

            $count++;
        }

        return $count;
    }

    private function scanAssignmentDeadlines(): int
    {
        $count = 0;
        $assignments = Assignment::query()
            ->where('due_date', '>', now())
            ->where('due_date', '<=', now()->addDay())
            ->get();

        $studentIds = $this->allStudentIds();

        foreach ($assignments as $assignment) {
            foreach ($studentIds as $studentId) {
                $student = User::query()->find($studentId);
                if (! $student) {
                    continue;
                }

                $submitted = AssignmentSubmission::query()
                    ->where('assignment_id', $assignment->assignment_id)
                    ->where('user_id', $studentId)
                    ->exists();

                if ($submitted) {
                    continue;
                }

                $this->preferences->ensureDefaults($student);
                $this->generator->createOrUpdate(
                    $student,
                    'assignment_deadline',
                    __('notifications.generated.assignment_deadline_title', ['title' => $assignment->assignment_name]),
                    __('notifications.generated.assignment_deadline_body', ['date' => $assignment->due_date?->format('d/m/Y H:i')]),
                    route('assignments.show', $assignment),
                    'assignment',
                    $assignment->assignment_id,
                    UserNotification::PRIORITY_NORMAL,
                    [],
                    "assignment_deadline:{$assignment->assignment_id}:24h"
                );
                $count++;
            }
        }

        return $count;
    }

    private function scanExamUpcoming(): int
    {
        $count = 0;
        $schedules = ExamSchedule::query()
            ->whereBetween('scheduled_date', [now(), now()->addDay()])
            ->where('is_completed', false)
            ->with('exam.course')
            ->get();

        foreach ($schedules as $schedule) {
            $courseId = $schedule->exam?->course_id;
            if (! $courseId) {
                continue;
            }

            $students = $this->roster->enrolledStudents(\App\Models\Course::find($courseId));
            foreach ($students as $student) {
                $this->preferences->ensureDefaults($student);
                $this->generator->createOrUpdate(
                    $student,
                    'exam_upcoming',
                    __('notifications.generated.exam_upcoming_title', ['title' => $schedule->exam?->exam_name ?? '']),
                    __('notifications.generated.exam_upcoming_body', ['date' => $schedule->scheduled_date?->format('d/m/Y H:i')]),
                    route('exams.attempt.lobby', $schedule),
                    'exam_schedule',
                    $schedule->schedule_id,
                    UserNotification::PRIORITY_NORMAL,
                    ['course_id' => $courseId],
                    "exam_upcoming:{$schedule->schedule_id}:user:{$student->user_id}"
                );
                $count++;
            }
        }

        return $count;
    }

    private function scanNearbyEvents(): int
    {
        $count = 0;
        $events = Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->get();

        foreach ($events as $event) {
            $students = $this->enrolledStudentsForEvent($event);
            foreach ($students as $student) {
                $this->preferences->ensureDefaults($student);
                $this->generator->createOrUpdate(
                    $student,
                    'event_nearby',
                    __('notifications.generated.event_nearby_title', ['title' => $event->title]),
                    __('notifications.generated.event_nearby_body', ['date' => $event->starts_at?->format('d/m/Y H:i')]),
                    route('events.show', $event),
                    'event',
                    $event->event_id,
                    UserNotification::PRIORITY_NORMAL,
                    [],
                    "event_nearby:{$event->event_id}:user:{$student->user_id}"
                );
                $count++;
            }
        }

        return $count;
    }

    private function scanUnclosedSessions(): int
    {
        $count = 0;
        $sessions = Session::query()
            ->whereNull('attendance_closed_at')
            ->whereDate('session_date', '<=', now()->subDays(7))
            ->with('course')
            ->get();

        foreach ($sessions as $session) {
            $staff = $this->roster->courseStaff($session->course_id);
            foreach ($staff as $instructor) {
                $days = $this->preferences->configValue($instructor, 'session_unclosed', 'unclosed_days', 7);
                if ($session->session_date->diffInDays(now()) < $days) {
                    continue;
                }

                $this->preferences->ensureDefaults($instructor);
                $this->generator->createOrUpdate(
                    $instructor,
                    'session_unclosed',
                    __('notifications.generated.session_unclosed_title', ['title' => $session->session_title ?? '']),
                    __('notifications.generated.session_unclosed_body', ['date' => $session->session_date?->format('d/m/Y')]),
                    route('sessions.show', $session),
                    'session',
                    $session->session_id,
                    UserNotification::PRIORITY_NORMAL,
                    [],
                    "session_unclosed:{$session->session_id}"
                );
                $count++;
            }
        }

        return $count;
    }

    private function scanAbsentStreaks(): int
    {
        $count = 0;
        $courses = \App\Models\Course::all();

        foreach ($courses as $course) {
            $staff = $this->roster->courseStaff($course->course_id);
            $sessions = Session::query()
                ->where('course_id', $course->course_id)
                ->whereNotNull('attendance_closed_at')
                ->orderByDesc('session_date')
                ->limit(3)
                ->get();

            if ($sessions->isEmpty()) {
                continue;
            }

            $students = $this->roster->enrolledStudents($course);
            foreach ($students as $student) {
                $absentCount = 0;
                foreach ($sessions as $session) {
                    $record = $student->attendances()->where('session_id', $session->session_id)->first();
                    if ($record && in_array($record->status, ['Absent', 'Late'], true)) {
                        $absentCount++;
                    }
                }

                if ($absentCount < 2) {
                    continue;
                }

                foreach ($staff as $instructor) {
                    $lookback = (int) $this->preferences->configValue($instructor, 'attendance_absent_streak', 'sessions_lookback', 3);
                    if ($absentCount < $lookback) {
                        continue;
                    }

                    $this->preferences->ensureDefaults($instructor);
                    $this->generator->createOrUpdate(
                        $instructor,
                        'attendance_absent_streak',
                        __('notifications.generated.absent_streak_title', ['name' => $student->displayName()]),
                        __('notifications.generated.absent_streak_body', ['count' => $absentCount]),
                        route('attendance.user-report', $student->user_id),
                        'user',
                        $student->user_id,
                        UserNotification::PRIORITY_NORMAL,
                        ['course_id' => $course->course_id],
                        "attendance_absent_streak:{$student->user_id}:{$course->course_id}"
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    private function scanUngradedAssignments(): int
    {
        $count = 0;
        $ungraded = AssignmentSubmission::query()
            ->whereNotNull('submitted_at')
            ->whereNull('points_earned')
            ->with(['assignment', 'user'])
            ->get()
            ->groupBy('assignment_id');

        $staffRoleIds = Role::staffRoleIds();
        $staffUsers = User::query()
            ->whereHas('userCourseRoles', fn ($q) => $q->whereIn('role_id', $staffRoleIds))
            ->get();

        foreach ($ungraded as $assignmentId => $submissions) {
            foreach ($staffUsers as $staff) {
                $this->preferences->ensureDefaults($staff);
                $assignment = $submissions->first()?->assignment;
                $this->generator->createOrUpdate(
                    $staff,
                    'assignment_needs_grading',
                    __('notifications.generated.needs_grading_title', ['title' => $assignment?->assignment_name ?? '']),
                    __('notifications.generated.needs_grading_body', ['count' => $submissions->count()]),
                    route('assignments.status', $assignmentId),
                    'assignment',
                    (int) $assignmentId,
                    UserNotification::PRIORITY_NORMAL,
                    [],
                    "assignment_needs_grading:{$assignmentId}"
                );
                $count++;
            }
        }

        return $count;
    }

    /** @return Collection<int, User> */
    private function enrolledStudentsForEvent(Event $event): Collection
    {
        if ($event->course_id) {
            return $this->roster->enrolledStudents(\App\Models\Course::find($event->course_id));
        }

        return User::query()
            ->whereIn('user_id', $this->allStudentIds())
            ->get();
    }

    /** @return Collection<int, User> */
    private function eventStaff(Event $event): Collection
    {
        if ($event->course_id) {
            return $this->roster->courseStaff($event->course_id);
        }

        $staffRoleIds = Role::staffRoleIds();

        return User::query()
            ->whereHas('userCourseRoles', fn ($q) => $q->whereIn('role_id', $staffRoleIds))
            ->get();
    }

    /** @return list<int> */
    private function allStudentIds(): array
    {
        $studentRoleIds = Role::studentRoleIds();
        if ($studentRoleIds->isEmpty()) {
            return [];
        }

        return UserCourseRole::query()
            ->whereIn('role_id', $studentRoleIds)
            ->distinct()
            ->pluck('user_id')
            ->all();
    }
}

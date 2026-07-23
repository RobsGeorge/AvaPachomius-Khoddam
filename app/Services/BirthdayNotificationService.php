<?php

namespace App\Services;

use App\Mail\DailyBirthdayAnnouncementMail;
use App\Mail\MonthlyBirthdayAnnouncementMail;
use App\Models\Course;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class BirthdayNotificationService
{
    public function __construct(
        private StudentRosterService $rosterService,
        private NotificationGeneratorService $generator,
        private NotificationPreferenceService $preferences,
    ) {}

    public function sendForCourse(Course $course, int $month, int $year): array
    {
        $students = $this->rosterService->enrolledStudents($course);
        $birthdayStudents = $this->rosterService->studentsWithBirthdayInMonth($students, $month);

        if ($birthdayStudents->isEmpty()) {
            return ['count' => 0, 'recipients' => collect()];
        }

        $staff = $this->rosterService->birthdayNotificationRecipients($course->course_id);
        $recipients = collect();

        foreach ($staff as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            Mail::to($recipient->email)->send(
                new MonthlyBirthdayAnnouncementMail($course, $birthdayStudents, $month, $year, $recipient)
            );
            $recipients->push($recipient);
        }

        return [
            'count' => $recipients->count(),
            'recipients' => $recipients,
        ];
    }

    public function sendForAllCourses(int $month, int $year): array
    {
        $courses = Course::query()->orderBy('title')->get();
        $summary = ['courses' => 0, 'emails' => 0];

        foreach ($courses as $course) {
            $result = $this->sendForCourse($course, $month, $year);
            if ($result['count'] > 0) {
                $summary['courses']++;
                $summary['emails'] += $result['count'];
            }
        }

        return $summary;
    }

    /** Email and portal notifications for course staff about students with birthdays on the given day. */
    public function notifyStaffForCourseToday(Course $course, ?Carbon $on = null): array
    {
        $timezone = config('attendance.timezone', config('app.timezone'));
        $on ??= now($timezone)->startOfDay();

        $students = $this->rosterService->enrolledStudents($course);
        $birthdayStudents = $this->rosterService->studentsWithBirthdayToday($students, $on);

        if ($birthdayStudents->isEmpty()) {
            return ['emails' => 0, 'portal' => 0, 'recipients' => collect()];
        }

        $staff = $this->rosterService->birthdayNotificationRecipients($course->course_id);
        $recipients = collect();
        $emailCount = 0;
        $portalCount = 0;
        $dateKey = $on->format('Y-m-d');
        $names = $birthdayStudents->map->displayName()->join(', ');

        foreach ($staff as $recipient) {
            if ($recipient->email) {
                Mail::to($recipient->email)->send(
                    new DailyBirthdayAnnouncementMail($course, $birthdayStudents, $on, $recipient)
                );
                $emailCount++;
            }

            $this->preferences->ensureDefaults($recipient);
            $this->generator->createOrUpdate(
                $recipient,
                'birthday_today',
                __('notifications.generated.birthday_title'),
                __('notifications.generated.birthday_body', ['names' => $names]),
                route('students.roster'),
                'course',
                $course->course_id,
                UserNotification::PRIORITY_NORMAL,
                ['course_id' => $course->course_id],
                "birthday_today:{$course->course_id}:{$dateKey}"
            );
            $portalCount++;
            $recipients->push($recipient);
        }

        return [
            'emails' => $emailCount,
            'portal' => $portalCount,
            'recipients' => $recipients,
        ];
    }

    public function notifyStaffForAllCoursesToday(?Carbon $on = null): array
    {
        $courses = Course::query()->orderBy('title')->get();
        $summary = ['courses' => 0, 'emails' => 0, 'portal' => 0];

        foreach ($courses as $course) {
            $result = $this->notifyStaffForCourseToday($course, $on);
            if ($result['emails'] > 0 || $result['portal'] > 0) {
                $summary['courses']++;
                $summary['emails'] += $result['emails'];
                $summary['portal'] += $result['portal'];
            }
        }

        return $summary;
    }
}

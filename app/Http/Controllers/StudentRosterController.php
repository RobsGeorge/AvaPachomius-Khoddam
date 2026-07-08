<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\BirthdayNotificationService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;

class StudentRosterController extends Controller
{
    public function __construct(
        private StudentRosterService $rosterService,
        private BirthdayNotificationService $notificationService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $courses = $this->rosterService->accessibleCourses($user);
        $timezone = config('attendance.timezone', config('app.timezone'));
        $now = now($timezone);

        if ($courses->isEmpty()) {
            return view('students.roster', [
                'courses' => $courses,
                'course' => null,
                'students' => collect(),
                'thisMonthBirthdays' => collect(),
                'nextMonthBirthdays' => collect(),
                'thisMonthLabel' => $now->translatedFormat('F Y'),
                'nextMonthLabel' => $now->copy()->addMonth()->translatedFormat('F Y'),
            ]);
        }

        $requestedCourseId = $request->input('course');
        $course = $requestedCourseId
            ? $courses->firstWhere('course_id', $requestedCourseId) ?? $courses->first()
            : $courses->first();

        $this->rosterService->authorizeCourse($user, $course->course_id);

        $students = $this->rosterService->enrolledStudents($course);
        $nextMonth = $now->copy()->addMonth();

        return view('students.roster', [
            'courses' => $courses,
            'course' => $course,
            'students' => $students,
            'thisMonthBirthdays' => $this->rosterService->studentsWithBirthdayInMonth($students, $now->month),
            'nextMonthBirthdays' => $this->rosterService->studentsWithBirthdayInMonth($students, $nextMonth->month),
            'thisMonthLabel' => $now->translatedFormat('F Y'),
            'nextMonthLabel' => $nextMonth->translatedFormat('F Y'),
        ]);
    }

    public function sendBirthdayAnnouncement(Course $course)
    {
        $this->rosterService->authorizeCourse(auth()->user(), $course->course_id);

        $timezone = config('attendance.timezone', config('app.timezone'));
        $now = now($timezone);

        $result = $this->notificationService->sendForCourse($course, $now->month, $now->year);

        if ($result['count'] === 0) {
            return redirect()
                ->route('students.roster', ['course' => $course->course_id])
                ->with('warning', __('students.no_birthdays_to_announce'));
        }

        $names = $result['recipients']
            ->map(fn ($recipient) => $recipient->displayName())
            ->implode(', ');

        return redirect()
            ->route('students.roster', ['course' => $course->course_id])
            ->with('success', __('students.announcement_sent', ['names' => $names]));
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\StudentRosterService;
use Illuminate\Http\Request;

class StudentBirthdaysController extends Controller
{
    public function __construct(
        private StudentRosterService $rosterService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isInstructorOrAdmin()) {
            return redirect()->route('students.roster');
        }

        $courses = $this->rosterService->studentEnrolledCourses($user);
        $timezone = config('attendance.timezone', config('app.timezone'));
        $now = now($timezone);

        if ($courses->isEmpty()) {
            return view('students.birthdays', [
                'courses' => $courses,
                'course' => null,
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

        $classmates = $this->rosterService->enrolledStudents($course);
        $nextMonth = $now->copy()->addMonth();

        return view('students.birthdays', [
            'courses' => $courses,
            'course' => $course,
            'thisMonthBirthdays' => $this->rosterService->studentsWithBirthdayInMonth($classmates, $now->month),
            'nextMonthBirthdays' => $this->rosterService->studentsWithBirthdayInMonth($classmates, $nextMonth->month),
            'thisMonthLabel' => $now->translatedFormat('F Y'),
            'nextMonthLabel' => $nextMonth->translatedFormat('F Y'),
        ]);
    }
}

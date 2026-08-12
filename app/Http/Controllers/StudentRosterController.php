<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\BirthdayNotificationService;
use App\Services\CourseContextService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentRosterController extends Controller
{
    public function __construct(
        private StudentRosterService $rosterService,
        private BirthdayNotificationService $notificationService,
        private CourseContextService $courseContext,
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
        $course = $this->courseContext->resolveAccessibleCourse(
            $user,
            $courses,
            $requestedCourseId !== null ? (string) $requestedCourseId : null,
        );

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

    /** F-08 — CSV export of course enrollments (student roster). */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $courses = $this->rosterService->accessibleCourses($user);
        abort_if($courses->isEmpty(), 403);

        $requestedCourseId = $request->input('course');
        $course = $this->courseContext->resolveAccessibleCourse(
            $user,
            $courses,
            $requestedCourseId !== null ? (string) $requestedCourseId : null,
        );
        $this->rosterService->authorizeCourse($user, $course->course_id);

        $students = $this->rosterService->enrolledStudents($course);
        $filename = 'enrollments-'.$course->course_id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($students, $course) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'course_id',
                'course_title',
                'national_id',
                'first_name',
                'second_name',
                'third_name',
                'email',
                'mobile_number',
                'date_of_birth',
            ]);

            foreach ($students as $student) {
                fputcsv($out, [
                    $course->course_id,
                    $course->title,
                    $student->national_id,
                    $student->first_name,
                    $student->second_name,
                    $student->third_name,
                    $student->email,
                    $student->mobile_number,
                    optional($student->date_of_birth)?->format('Y-m-d') ?? $student->date_of_birth,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\StudentRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BirthdayController extends Controller
{
    public function __construct(
        private StudentRosterService $rosterService,
        private CourseContextService $courseContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $courses = $user->isInstructorOrAdmin() || ($user->is_superadmin ?? false)
            ? $this->rosterService->accessibleCourses($user)
            : $this->rosterService->studentEnrolledCourses($user);

        $timezone = config('attendance.timezone', config('app.timezone'));
        $now = now($timezone);

        if ($courses->isEmpty()) {
            return response()->json([
                'data' => [
                    'course_id' => null,
                    'this_month' => [],
                    'next_month' => [],
                ],
                'meta' => [
                    'this_month_label' => $now->translatedFormat('F Y'),
                    'next_month_label' => $now->copy()->addMonth()->translatedFormat('F Y'),
                ],
            ]);
        }

        $requestedCourseId = $request->query('course_id');
        $course = $this->courseContext->resolveAccessibleCourse(
            $user,
            $courses,
            $requestedCourseId !== null ? (string) $requestedCourseId : null,
        );

        abort_unless($course, 404);
        $this->rosterService->authorizeCourse($user, $course->course_id);
        $classmates = $this->rosterService->enrolledStudents($course);
        $nextMonth = $now->copy()->addMonth();

        $mapStudent = fn (User $student) => [
            'user_id' => $student->user_id,
            'display_name' => $student->displayName(),
            'birth_date' => $student->birth_date ?? $student->date_of_birth ?? null,
        ];

        return response()->json([
            'data' => [
                'course_id' => $course->course_id,
                'this_month' => $this->rosterService
                    ->studentsWithBirthdayInMonth($classmates, $now->month)
                    ->map($mapStudent)
                    ->values(),
                'next_month' => $this->rosterService
                    ->studentsWithBirthdayInMonth($classmates, $nextMonth->month)
                    ->map($mapStudent)
                    ->values(),
            ],
            'meta' => [
                'this_month_label' => $now->translatedFormat('F Y'),
                'next_month_label' => $nextMonth->translatedFormat('F Y'),
                'courses' => $courses->map(fn ($c) => [
                    'course_id' => $c->course_id,
                    'title' => method_exists($c, 'localizedTitle') ? $c->localizedTitle() : $c->title,
                ])->values(),
            ],
        ]);
    }
}

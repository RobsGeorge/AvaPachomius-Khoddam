<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseApplicationForm;
use App\Models\User;
use App\Services\CourseApplicationService;
use App\Services\StudentRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseApplicationController extends Controller
{
    public function __construct(
        private CourseApplicationService $applications,
        private StudentRosterService $roster,
    ) {}

    public function available(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $enrolledIds = $this->roster->studentEnrolledCourses($user)->pluck('course_id');

        $openForms = CourseApplicationForm::query()
            ->where('is_enabled', true)
            ->with('course')
            ->orderBy('title')
            ->get()
            ->filter(fn (CourseApplicationForm $form) => ! $enrolledIds->contains($form->course_id))
            ->values();

        return response()->json([
            'data' => $openForms->map(function (CourseApplicationForm $form) use ($user) {
                $course = $form->course;

                return [
                    'form_id' => $form->form_id ?? $form->getKey(),
                    'title' => $form->title,
                    'course_id' => $form->course_id,
                    'course_title' => $course
                        ? (method_exists($course, 'localizedTitle') ? $course->localizedTitle() : $course->title)
                        : null,
                    'application_status' => $this->applications->courseApplicationStatus($user, (int) $form->course_id),
                    'is_approved' => $this->applications->isApprovedForCourse($user, (int) $form->course_id),
                ];
            }),
        ]);
    }

    public function status(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $latest = $this->applications->latestForUserCourse($user, $course);

        return response()->json([
            'data' => [
                'course_id' => $course->course_id,
                'status' => $latest?->status ?? $this->applications->courseApplicationStatus($user, (int) $course->course_id),
                'is_approved' => $this->applications->isApprovedForCourse($user, (int) $course->course_id),
                'application' => $latest ? [
                    'id' => $latest->id ?? $latest->getKey(),
                    'version' => $latest->version ?? null,
                    'status' => $latest->status,
                    'submitted_at' => $latest->submitted_at?->toIso8601String() ?? $latest->created_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}

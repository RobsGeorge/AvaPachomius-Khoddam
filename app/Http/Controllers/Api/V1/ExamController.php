<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    use AuthorizesStudentCourse;

    public function index(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $course, 'exam.view');

        $exams = Exam::query()
            ->with(['schedules'])
            ->where('course_id', $course->course_id)
            ->where('is_published', true)
            ->orderBy('exam_name')
            ->get();

        return response()->json([
            'data' => $exams->map(function (Exam $exam) {
                return [
                    'exam_id' => $exam->exam_id,
                    'exam_name' => $exam->exam_name,
                    'exam_type' => $exam->exam_type,
                    'delivery_mode' => $exam->delivery_mode,
                    'duration_minutes' => $exam->duration_minutes,
                    'total_points' => $exam->total_points,
                    'schedules' => ($exam->schedules ?? collect())->map(fn ($s) => [
                        'schedule_id' => $s->schedule_id ?? $s->getKey(),
                        'starts_at' => $s->starts_at?->toIso8601String() ?? $s->start_at?->toIso8601String(),
                        'ends_at' => $s->ends_at?->toIso8601String() ?? $s->end_at?->toIso8601String(),
                    ])->values(),
                ];
            })->values(),
            'meta' => [
                'note' => 'Full attempt take/save/submit remains on web for Wave C; mobile may deep-link to web or call timed endpoints later.',
            ],
        ]);
    }
}

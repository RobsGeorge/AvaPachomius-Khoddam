<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSchedule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourseAllowsAssessments
{
    public function handle(Request $request, Closure $next): Response
    {
        $course = $this->resolveCourse($request);

        if ($course && ! $course->allowsStudentAssessments()) {
            abort(403, __('course_graduation.errors.course_not_active'));
        }

        return $next($request);
    }

    private function resolveCourse(Request $request): ?Course
    {
        $schedule = $request->route('schedule');

        if ($schedule instanceof ExamSchedule) {
            return $schedule->exam?->course;
        }

        if (is_string($schedule) || is_numeric($schedule)) {
            $model = ExamSchedule::with('exam.course')->find($schedule);

            return $model?->exam?->course;
        }

        $exam = $request->route('exam');

        if ($exam instanceof Exam) {
            return $exam->course;
        }

        if (is_string($exam) || is_numeric($exam)) {
            return Exam::with('course')->find($exam)?->course;
        }

        return null;
    }
}

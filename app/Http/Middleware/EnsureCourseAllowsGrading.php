<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\GradeItem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourseAllowsGrading
{
    public function handle(Request $request, Closure $next): Response
    {
        $course = $this->resolveCourse($request);

        if ($course && ! $course->allowsGradeEditing()) {
            abort(403, __('course_graduation.errors.grading_locked'));
        }

        return $next($request);
    }

    private function resolveCourse(Request $request): ?Course
    {
        $course = $request->route('course');

        if ($course instanceof Course) {
            return $course;
        }

        if (is_string($course) || is_numeric($course)) {
            return Course::find($course);
        }

        $item = $request->route('item');

        if ($item instanceof GradeItem) {
            return $item->category?->course;
        }

        if (is_string($item) || is_numeric($item)) {
            $gradeItem = GradeItem::with('category.course')->find($item);

            return $gradeItem?->category?->course;
        }

        return null;
    }
}

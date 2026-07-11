<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGraduationStudent;
use Illuminate\Support\Facades\Auth;

class FinalGradesController extends Controller
{
    public function show(string $courseId)
    {
        $course = Course::findOrFail($courseId);

        abort_unless($course->areGradesAnnounced(), 403, __('course_graduation.grades_not_announced'));

        $graduation = $course->latestGraduation()->first();
        abort_if(! $graduation, 404);

        $record = CourseGraduationStudent::query()
            ->where('course_graduation_id', $graduation->id)
            ->where('user_id', Auth::id())
            ->with('certificate')
            ->firstOrFail();

        return view('course-graduation.final-grades', compact('course', 'record', 'graduation'));
    }
}

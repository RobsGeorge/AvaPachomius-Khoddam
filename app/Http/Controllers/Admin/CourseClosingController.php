<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Role;
use App\Models\UserCourseRole;
use App\Services\CourseClosingService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;

class CourseClosingController extends Controller
{
    public function __construct(
        private CourseClosingService $closing,
        private StudentRosterService $roster,
    ) {}

    public function show(string $courseId)
    {
        $this->roster->authorizeCourse(auth()->user(), $courseId);

        $course = Course::with(['gradeCategories.items.grades', 'sessions'])->findOrFail($courseId);
        $checklist = $this->closing->checklist($course);
        $preview = $course->hasGraduationCriteria()
            ? $this->closing->preview($course)
            : collect();

        $studentRoleId = Role::query()->whereRaw('LOWER(role_name) = ?', ['student'])->value('role_id');
        $enrollments = UserCourseRole::where('course_id', $course->course_id)
            ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
            ->with('user')
            ->get()
            ->keyBy('user_id');

        return view('admin.course-closing.show', compact('course', 'checklist', 'preview', 'enrollments'));
    }

    public function lockGrading(string $courseId)
    {
        $this->roster->authorizeCourse(auth()->user(), $courseId);
        $course = Course::findOrFail($courseId);
        $this->closing->lockGrading($course, auth()->user());

        return redirect()
            ->route('courses.closing.show', $course->course_id)
            ->with('success', __('course_graduation.success.grading_locked'));
    }

    public function updateGrace(Request $request, string $courseId)
    {
        $this->roster->authorizeCourse(auth()->user(), $courseId);
        $course = Course::findOrFail($courseId);

        $data = $request->validate([
            'grace_marks_enabled'    => 'nullable|boolean',
            'max_grace_marks'        => 'nullable|numeric|min:0|max:100',
            'grace_eligibility_mode' => 'nullable|in:manual',
            'grace'                  => 'nullable|array',
            'grace.*.eligible_for_grace' => 'nullable|boolean',
            'grace.*.pending_grace_marks' => 'nullable|numeric|min:0|max:100',
        ]);

        $this->closing->updateGraceConfig($course, [
            'grace_marks_enabled'    => $request->boolean('grace_marks_enabled'),
            'max_grace_marks'        => $data['max_grace_marks'] ?? 0,
            'grace_eligibility_mode' => $data['grace_eligibility_mode'] ?? Course::GRACE_MODE_MANUAL,
        ]);

        if ($request->has('grace')) {
            $this->closing->updateGraceMarks($course->fresh(), $request->input('grace', []));
        }

        return redirect()
            ->route('courses.closing.show', $course->course_id)
            ->with('success', __('course_graduation.success.grace_saved'));
    }

    public function announce(string $courseId)
    {
        $this->roster->authorizeCourse(auth()->user(), $courseId);
        $course = Course::findOrFail($courseId);
        $this->closing->announce($course, auth()->user());

        return redirect()
            ->route('courses.closing.show', $course->course_id)
            ->with('success', __('course_graduation.success.announced'));
    }

    public function close(Request $request, string $courseId)
    {
        $this->roster->authorizeCourse(auth()->user(), $courseId);
        $course = Course::findOrFail($courseId);
        $this->closing->close($course, auth()->user(), $request->boolean('archive_staff', true));

        return redirect()
            ->route('graduation.show', $course->course_id)
            ->with('success', __('course_graduation.success.closed'));
    }
}

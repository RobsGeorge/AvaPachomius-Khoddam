<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\GraduationService;
use Illuminate\Http\Request;

class GraduationController extends Controller
{
    public function __construct(
        private GraduationService $graduation,
    ) {}

    /** Overview across all courses. */
    public function index()
    {
        $courses = Course::orderBy('year', 'desc')->orderBy('title')->get();

        $summaries = $courses->map(function (Course $course) {
            $course->load(['gradeCategories.items.grades']);
            $students = $this->enrolledStudents($course->course_id);
            $evaluations = $course->hasGraduationCriteria()
                ? $this->graduation->evaluateCourse($course, $students)
                : collect();

            return [
                'course'     => $course,
                'students'   => $students->count(),
                'eligible'   => $evaluations->where('eligible', true)->count(),
                'configured' => $course->hasGraduationCriteria(),
            ];
        });

        $unconfiguredCount = $summaries->where('configured', false)->count();

        return view('graduation.index', compact('summaries', 'unconfiguredCount'));
    }

    public function show(string $courseId)
    {
        $course = Course::with(['gradeCategories.items.grades'])->findOrFail($courseId);
        $students = $this->enrolledStudents($courseId);
        $criteriaConfigured = $course->hasGraduationCriteria();
        $evaluations = $criteriaConfigured
            ? $this->graduation->evaluateCourse($course, $students)
            : $this->graduation->evaluateCoursePreview($course, $students);
        $eligible = $evaluations->where('eligible', true)->values();

        return view('graduation.show', compact('course', 'evaluations', 'eligible', 'criteriaConfigured'));
    }

    /** Admin: configure graduation percentages for all courses. */
    public function settings()
    {
        $courses = Course::orderBy('year', 'desc')->orderBy('title')->get();

        return view('graduation.settings', compact('courses'));
    }

    public function updateSettings(Request $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);

        $data = $request->validate([
            'passing_percentage'        => 'required|numeric|min:0|max:100',
            'min_attendance_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $course->update($data);

        return redirect()
            ->route('admin.graduation-settings.index')
            ->with('success', __('pages.graduation_settings_saved_for', ['course' => $course->title]));
    }

    private function enrolledStudents(string $courseId)
    {
        $studentRoleId = Role::where('role_name', 'Student')->value('role_id');

        $studentIds = UserCourseRole::where('course_id', $courseId)
            ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
            ->pluck('user_id')
            ->unique();

        return User::whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->get();
    }
}

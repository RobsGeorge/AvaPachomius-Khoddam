<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\GradeCategory;

class GradeCategoryController extends Controller
{
    /** Admin/instructor grade management panel for a course */
    public function admin(string $courseId)
    {
        $course = Course::with([
            'gradeCategories.items.grades',
        ])->findOrFail($courseId);

        $totalWeight = $course->gradeCategories->sum('weight_percentage');

        // Count enrolled students (role = Student)
        $studentCount = $this->getStudentCount($courseId);

        return view('grades.admin', compact('course', 'totalWeight', 'studentCount'));
    }

    public function store(Request $request, string $courseId)
    {
        $request->validate([
            'type'             => 'required|in:exam,quiz,presentation,project,attendance,other',
            'name'             => 'required|string|max:100',
            'weight_percentage'=> 'required|numeric|min:0|max:100',
            'ordering'         => 'nullable|integer|min:0',
        ]);

        Course::findOrFail($courseId);

        GradeCategory::create([
            'course_id'        => $courseId,
            'type'             => $request->type,
            'name'             => $request->name,
            'weight_percentage'=> $request->weight_percentage,
            'ordering'         => $request->ordering ?? 0,
        ]);

        return redirect()
            ->route('grades.admin', $courseId)
            ->with('success', 'تم إضافة فئة التقييم');
    }

    public function update(Request $request, string $categoryId)
    {
        $request->validate([
            'name'              => 'required|string|max:100',
            'weight_percentage' => 'required|numeric|min:0|max:100',
            'ordering'          => 'nullable|integer|min:0',
        ]);

        $category = GradeCategory::findOrFail($categoryId);
        $category->update($request->only('name', 'weight_percentage', 'ordering'));

        return redirect()
            ->route('grades.admin', $category->course_id)
            ->with('success', 'تم تحديث فئة التقييم');
    }

    public function destroy(string $categoryId)
    {
        $category = GradeCategory::findOrFail($categoryId);
        $courseId = $category->course_id;
        $category->delete();

        return redirect()
            ->route('grades.admin', $courseId)
            ->with('success', 'تم حذف الفئة وجميع بنودها');
    }

    private function getStudentCount(string $courseId): int
    {
        $studentRoleId = \App\Models\Role::where('role_name', 'Student')->value('role_id');
        if (!$studentRoleId) return 0;
        return \App\Models\UserCourseRole::where('course_id', $courseId)
            ->where('role_id', $studentRoleId)
            ->count();
    }
}

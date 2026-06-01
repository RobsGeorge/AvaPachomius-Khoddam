<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\GradeItem;
use App\Models\StudentGrade;
use App\Models\UserCourseRole;
use App\Models\User;
use App\Models\Role;
use App\Models\GradeCategory;
use Illuminate\Support\Facades\Auth;

class StudentGradeController extends Controller
{
    /** Student view: own grades for a course */
    public function show(string $courseId)
    {
        $course  = Course::with(['gradeCategories.items.grades'])->findOrFail($courseId);
        $userId  = Auth::user()->user_id;
        $total   = $course->studentTotalGrade($userId);

        return view('grades.show', compact('course', 'userId', 'total'));
    }

    /** Admin view: all students' grades for a course */
    public function courseReport(string $courseId)
    {
        $course   = Course::with(['gradeCategories.items.grades'])->findOrFail($courseId);
        $students = $this->enrolledStudents($courseId);

        $report = $students->map(function (User $student) use ($course) {
            $total = $course->studentTotalGrade($student->user_id);
            return [
                'user'         => $student,
                'total'        => $total,
                'letter'       => GradeCategory::letterGrade($total),
                'letter_ar'    => GradeCategory::letterGradeAr($total),
                'color'        => GradeCategory::gradeColor($total),
                'categories'   => $course->gradeCategories->map(fn ($cat) => [
                    'name'         => $cat->name,
                    'weight'       => $cat->weight_percentage,
                    'raw'          => $cat->studentRawScore($student->user_id),
                    'max'          => $cat->maxRawScore(),
                    'contribution' => $cat->studentContribution($student->user_id),
                ]),
            ];
        })->sortByDesc('total')->values();

        $avg = $report->avg('total');

        return view('grades.course-report', compact('course', 'report', 'avg'));
    }

    /** Admin: show grading form for a specific item */
    public function itemScores(string $itemId)
    {
        $item     = GradeItem::with(['grades.student', 'category.course'])->findOrFail($itemId);
        $course   = $item->category->course;
        $students = $this->enrolledStudents($course->course_id);

        // Key existing grades by user_id for easy lookup in the view
        $existingGrades = $item->grades->keyBy('user_id');

        return view('grades.item-scores', compact('item', 'course', 'students', 'existingGrades'));
    }

    /** Admin: save all scores for a specific item (bulk upsert) */
    public function bulkSave(Request $request, string $itemId)
    {
        $item   = GradeItem::with('category')->findOrFail($itemId);
        $scores = $request->input('scores', []);
        $notes  = $request->input('notes', []);

        foreach ($scores as $userId => $score) {
            if ($score === null || $score === '') {
                // Allow clearing a grade
                StudentGrade::where('item_id', $itemId)
                    ->where('user_id', $userId)
                    ->delete();
                continue;
            }

            $scoreVal = (float) $score;
            if ($scoreVal < 0 || $scoreVal > $item->max_score) continue;

            StudentGrade::updateOrCreate(
                ['item_id' => $itemId, 'user_id' => $userId],
                [
                    'score'        => $scoreVal,
                    'notes'        => $notes[$userId] ?? null,
                    'graded_by_id' => Auth::user()->user_id,
                    'graded_at'    => now(),
                ]
            );
        }

        return redirect()
            ->route('grade-items.scores', $itemId)
            ->with('success', 'تم حفظ الدرجات بنجاح');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

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

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
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentGradeController extends Controller
{
    /** Student view: own grades for a course */
    public function show(string $courseId)
    {
        $course = Course::with(['gradeCategories.items.grades'])->findOrFail($courseId);

        if ($course->areGradesAnnounced() && Auth::user()?->isStudent()) {
            return redirect()->route('courses.final-grades', $course->course_id);
        }

        $userId = Auth::user()->user_id;
        $total  = $course->studentTotalGrade($userId);

        return view('grades.show', compact('course', 'userId', 'total'));
    }

    /** Admin view: all students' grades for a course */
    public function courseReport(string $courseId)
    {
        $course = Course::with(['gradeCategories.items.grades'])->findOrFail($courseId);
        $report = $this->buildCourseReport($course);
        $avg = $report->avg('total');

        return view('grades.course-report', compact('course', 'report', 'avg'));
    }

    /** F-08 — CSV gradebook export (category summary + totals). */
    public function exportCsv(string $courseId): StreamedResponse
    {
        $course = Course::with(['gradeCategories.items.grades'])->findOrFail($courseId);
        $report = $this->buildCourseReport($course);
        $categories = $course->gradeCategories;
        $filename = 'gradebook-'.$course->course_id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($report, $categories) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Arabic names correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $headers = [
                'national_id',
                'first_name',
                'second_name',
                'email',
            ];
            foreach ($categories as $cat) {
                $slug = preg_replace('/\s+/', '_', (string) $cat->name) ?: 'category';
                $headers[] = $slug.'_raw';
                $headers[] = $slug.'_max';
                $headers[] = $slug.'_contribution';
            }
            $headers[] = 'total';
            $headers[] = 'letter';
            $headers[] = 'letter_ar';
            fputcsv($out, $headers);

            foreach ($report as $row) {
                /** @var User $student */
                $student = $row['user'];
                $line = [
                    $student->national_id,
                    $student->first_name,
                    $student->second_name,
                    $student->email,
                ];
                foreach ($row['categories'] as $cat) {
                    $line[] = $cat['raw'];
                    $line[] = $cat['max'];
                    $line[] = $cat['contribution'];
                }
                $line[] = $row['total'];
                $line[] = $row['letter'];
                $line[] = $row['letter_ar'];
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
        $item   = GradeItem::with('category.course')->findOrFail($itemId);
        $course = $item->category->course;

        abort_unless($course->allowsGradeEditing(), 403, __('course_graduation.errors.grading_locked'));

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

            $grade = StudentGrade::query()
                ->where('item_id', $itemId)
                ->where('user_id', $userId)
                ->first();

            if ($grade) {
                app(\App\Services\NotificationScannerService::class)->notifyGradePosted($grade);
            }
        }

        return redirect()
            ->route('grade-items.scores', $itemId)
            ->with('success', 'تم حفظ الدرجات بنجاح');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function buildCourseReport(Course $course)
    {
        $students = $this->enrolledStudents((string) $course->course_id);

        return $students->map(function (User $student) use ($course) {
            $total = $course->studentTotalGrade($student->user_id);

            return [
                'user' => $student,
                'total' => $total,
                'letter' => GradeCategory::letterGrade($total),
                'letter_ar' => GradeCategory::letterGradeAr($total),
                'color' => GradeCategory::gradeColor($total),
                'categories' => $course->gradeCategories->map(fn ($cat) => [
                    'name' => $cat->name,
                    'weight' => $cat->weight_percentage,
                    'raw' => $cat->studentRawScore($student->user_id),
                    'max' => $cat->maxRawScore(),
                    'contribution' => $cat->studentContribution($student->user_id),
                ]),
            ];
        })->sortByDesc('total')->values();
    }

    private function enrolledStudents(string $courseId)
    {
        $studentRoleIds = Role::studentRoleIds();

        $studentIds = UserCourseRole::where('course_id', $courseId)
            ->when($studentRoleIds->isNotEmpty(), fn ($q) => $q->whereIn('role_id', $studentRoleIds))
            ->pluck('user_id')
            ->unique();

        return User::whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->get();
    }
}

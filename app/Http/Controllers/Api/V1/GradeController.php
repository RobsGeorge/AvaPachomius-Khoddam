<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    use AuthorizesStudentCourse;

    public function show(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $course, 'grade.view');

        $course->load(['gradeCategories.items.grades']);
        $userId = $user->user_id;
        $total = $course->studentTotalGrade($userId);

        $categories = $course->gradeCategories->map(function ($cat) use ($userId) {
            return [
                'category_id' => $cat->category_id ?? $cat->getKey(),
                'name' => $cat->name,
                'weight_percentage' => $cat->weight_percentage,
                'raw' => $cat->studentRawScore($userId),
                'max' => $cat->maxRawScore(),
                'contribution' => $cat->studentContribution($userId),
                'items' => $cat->items->map(function ($item) use ($userId) {
                    $grade = $item->grades->firstWhere('user_id', $userId);

                    return [
                        'item_id' => $item->item_id ?? $item->getKey(),
                        'name' => $item->name,
                        'max_score' => $item->max_score ?? null,
                        'score' => $grade?->score,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'course_id' => $course->course_id,
                'total' => $total,
                'letter' => GradeCategory::letterGrade($total),
                'letter_ar' => GradeCategory::letterGradeAr($total),
                'grades_announced' => $course->areGradesAnnounced(),
                'categories' => $categories,
            ],
        ]);
    }

    public function finalGrades(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCoursePermission($user, $course, 'grade.view');

        $total = $course->studentTotalGrade($user->user_id);

        return response()->json([
            'data' => [
                'course_id' => $course->course_id,
                'total' => $total,
                'letter' => GradeCategory::letterGrade($total),
                'letter_ar' => GradeCategory::letterGradeAr($total),
                'grades_announced' => $course->areGradesAnnounced(),
            ],
        ]);
    }
}

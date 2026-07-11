<?php

namespace App\Services;

use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GraduationService
{
    public function __construct(
        private CourseEnrollmentService $enrollment,
    ) {}

    /** @return Collection<int, float> user_id => attendance percentage */
    public function attendancePercentagesForCourse(Course $course): Collection
    {
        $lateFactor = $this->lateAttendanceFactor();

        $rows = DB::table('attendance')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('session.course_id', $course->course_id)
            ->groupBy('attendance.user_id')
            ->select([
                'attendance.user_id',
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END), 0) as full_attended'),
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status = "Late" THEN 1 ELSE 0 END), 0) as late'),
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission", "Absent", "Late") THEN 1 ELSE 0 END), 0) as recorded'),
            ])
            ->get();

        return $rows->mapWithKeys(function ($row) use ($lateFactor) {
            $recorded = (int) $row->recorded;
            $attended = (float) $row->full_attended + ((float) $row->late * $lateFactor);
            $pct = $recorded > 0
                ? round(($attended / $recorded) * 100, 2)
                : 0.0;

            return [(int) $row->user_id => $pct];
        });
    }

    private function lateAttendanceFactor(): float
    {
        $policy = \App\Models\AttendancePolicy::current();

        if (! $policy->is_enabled) {
            return 0.0;
        }

        return max(0, min(1, $policy->late_grade_percentage / 100));
    }

    /**
     * @return array{
     *   user: User,
     *   attendance_pct: float,
     *   raw_total_grade: float,
     *   grace_marks_applied: float,
     *   total_grade: float,
     *   letter: string,
     *   letter_ar: string,
     *   color: string,
     *   eligible: bool,
     *   graduated: bool,
     *   attendance_pass: bool,
     *   grade_pass: bool,
     *   failure_reason: string|null
     * }
     */
    public function evaluateStudent(Course $course, User $student, float $attendancePct, float $graceMarks = 0.0): array
    {
        $rawTotal = $course->studentTotalGrade($student->user_id);
        $graceMarks = $this->clampGrace($course, $graceMarks);
        $totalGrade = min(100, round($rawTotal + $graceMarks, 2));
        $minAttendance = $course->effectiveMinAttendancePercentage();
        $passingGrade = $course->effectivePassingPercentage();
        $criteriaConfigured = $course->hasGraduationCriteria();

        $attendancePassed = $attendancePct >= $minAttendance;

        if (! $attendancePassed) {
            return [
                'user'                 => $student,
                'attendance_pct'       => $attendancePct,
                'raw_total_grade'      => $rawTotal,
                'grace_marks_applied'  => 0.0,
                'total_grade'          => 0.0,
                'letter'               => 'F',
                'letter_ar'            => GradeCategory::letterGradeAr(0),
                'color'                => 'danger',
                'eligible'             => false,
                'graduated'            => false,
                'attendance_pass'      => false,
                'grade_pass'           => false,
                'failure_reason'       => 'attendance',
            ];
        }

        if (! $criteriaConfigured) {
            return [
                'user'                 => $student,
                'attendance_pct'       => $attendancePct,
                'raw_total_grade'      => $rawTotal,
                'grace_marks_applied'  => $graceMarks,
                'total_grade'          => $totalGrade,
                'letter'               => GradeCategory::letterGrade($totalGrade),
                'letter_ar'            => GradeCategory::letterGradeAr($totalGrade),
                'color'                => GradeCategory::gradeColor($totalGrade),
                'eligible'             => false,
                'graduated'            => false,
                'attendance_pass'      => true,
                'grade_pass'           => $totalGrade >= $passingGrade,
                'failure_reason'       => null,
            ];
        }

        $gradePassed = $totalGrade >= $passingGrade;

        return [
            'user'                 => $student,
            'attendance_pct'       => $attendancePct,
            'raw_total_grade'      => $rawTotal,
            'grace_marks_applied'  => $graceMarks,
            'total_grade'          => $totalGrade,
            'letter'               => GradeCategory::letterGrade($totalGrade),
            'letter_ar'            => GradeCategory::letterGradeAr($totalGrade),
            'color'                => GradeCategory::gradeColor($totalGrade),
            'eligible'             => $gradePassed,
            'graduated'            => $gradePassed,
            'attendance_pass'      => true,
            'grade_pass'           => $gradePassed,
            'failure_reason'       => $gradePassed ? null : 'grade',
        ];
    }

    /** @param array<int, float> $graceMap user_id => grace marks */
    public function evaluateWithGrace(Course $course, Collection $students, array $graceMap = []): Collection
    {
        $attendanceMap = $this->attendancePercentagesForCourse($course);

        return $students
            ->map(function (User $student) use ($course, $attendanceMap, $graceMap) {
                $grace = (float) ($graceMap[$student->user_id] ?? 0);

                return $this->evaluateStudent(
                    $course,
                    $student,
                    (float) ($attendanceMap[$student->user_id] ?? 0),
                    $grace
                );
            })
            ->sortByDesc(fn (array $row) => [$row['graduated'], $row['total_grade']])
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function evaluateCourse(Course $course, Collection $students): Collection
    {
        return $this->evaluateWithGrace($course, $students);
    }

    /** Preview rows when admin has not configured graduation criteria yet. */
    public function evaluateCoursePreview(Course $course, Collection $students): Collection
    {
        $attendanceMap = $this->attendancePercentagesForCourse($course);

        return $students
            ->map(function (User $student) use ($course, $attendanceMap) {
                $attendancePct = (float) ($attendanceMap[$student->user_id] ?? 0);
                $totalGrade = $course->studentTotalGrade($student->user_id);

                return [
                    'user'                => $student,
                    'attendance_pct'      => $attendancePct,
                    'raw_total_grade'     => $totalGrade,
                    'grace_marks_applied' => 0.0,
                    'total_grade'         => $totalGrade,
                    'letter'              => GradeCategory::letterGrade($totalGrade),
                    'letter_ar'           => GradeCategory::letterGradeAr($totalGrade),
                    'color'               => GradeCategory::gradeColor($totalGrade),
                    'eligible'            => false,
                    'graduated'           => false,
                    'attendance_pass'     => null,
                    'grade_pass'          => null,
                    'failure_reason'      => null,
                ];
            })
            ->sortByDesc('total_grade')
            ->values();
    }

    /** @return array<string, mixed> */
    public function buildGradesDetailJson(Course $course, int $userId): array
    {
        $course->loadMissing(['gradeCategories.items.grades']);

        return [
            'categories' => $course->gradeCategories->map(fn ($cat) => [
                'name'         => $cat->name,
                'type'         => $cat->type,
                'weight'       => $cat->weight_percentage,
                'raw'          => $cat->studentRawScore($userId),
                'max'          => $cat->maxRawScore(),
                'contribution' => $cat->studentContribution($userId),
                'items'        => $cat->items->map(function ($item) use ($userId) {
                    $grade = $item->grades->firstWhere('user_id', $userId);

                    return [
                        'title'     => $item->title,
                        'max_score' => $item->max_score,
                        'score'     => $grade?->score,
                        'notes'     => $grade?->notes,
                    ];
                })->values()->all(),
            ])->values()->all(),
        ];
    }

    public function clampGrace(Course $course, float $graceMarks): float
    {
        if (! $course->grace_marks_enabled) {
            return 0.0;
        }

        return max(0, min((float) $course->max_grace_marks, $graceMarks));
    }

    /** @return Collection<int, User> */
    public function enrolledStudents(Course $course): Collection
    {
        return $this->enrollment->enrolledStudents($course->course_id);
    }
}

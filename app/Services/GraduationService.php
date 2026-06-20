<?php

namespace App\Services;

use App\Models\AttendancePolicy;
use App\Models\Course;
use App\Models\GradeCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GraduationService
{
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
        $policy = AttendancePolicy::current();

        if (! $policy->is_enabled) {
            return 0.0;
        }

        return max(0, min(1, $policy->late_grade_percentage / 100));
    }

    /**
     * @return array{
     *   user: User,
     *   attendance_pct: float,
     *   total_grade: float,
     *   letter: string,
     *   letter_ar: string,
     *   color: string,
     *   eligible: bool,
     *   attendance_pass: bool,
     *   grade_pass: bool,
     *   failure_reason: string|null
     * }
     */
    public function evaluateStudent(Course $course, User $student, float $attendancePct): array
    {
        $totalGrade = $course->studentTotalGrade($student->user_id);
        $minAttendance = $course->effectiveMinAttendancePercentage();
        $passingGrade = $course->effectivePassingPercentage();
        $criteriaConfigured = $course->hasGraduationCriteria();

        $attendancePassed = $attendancePct >= $minAttendance;

        if (! $attendancePassed) {
            return [
                'user'            => $student,
                'attendance_pct'  => $attendancePct,
                'total_grade'     => $totalGrade,
                'letter'          => 'F',
                'letter_ar'       => GradeCategory::letterGradeAr(0),
                'color'           => 'danger',
                'eligible'        => false,
                'attendance_pass' => false,
                'grade_pass'      => false,
                'failure_reason'  => 'attendance',
            ];
        }

        if (! $criteriaConfigured) {
            return [
                'user'            => $student,
                'attendance_pct'  => $attendancePct,
                'total_grade'     => $totalGrade,
                'letter'          => GradeCategory::letterGrade($totalGrade),
                'letter_ar'       => GradeCategory::letterGradeAr($totalGrade),
                'color'           => GradeCategory::gradeColor($totalGrade),
                'eligible'        => false,
                'attendance_pass' => true,
                'grade_pass'      => $totalGrade >= $passingGrade,
                'failure_reason'  => null,
            ];
        }

        $gradePassed = $totalGrade >= $passingGrade;

        return [
            'user'            => $student,
            'attendance_pct'  => $attendancePct,
            'total_grade'     => $totalGrade,
            'letter'          => GradeCategory::letterGrade($totalGrade),
            'letter_ar'       => GradeCategory::letterGradeAr($totalGrade),
            'color'           => GradeCategory::gradeColor($totalGrade),
            'eligible'        => $criteriaConfigured && $gradePassed,
            'attendance_pass' => true,
            'grade_pass'      => $gradePassed,
            'failure_reason'  => $gradePassed ? null : 'grade',
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function evaluateCourse(Course $course, Collection $students): Collection
    {
        $attendanceMap = $this->attendancePercentagesForCourse($course);

        return $students
            ->map(fn (User $student) => $this->evaluateStudent(
                $course,
                $student,
                (float) ($attendanceMap[$student->user_id] ?? 0)
            ))
            ->sortByDesc(fn (array $row) => [$row['eligible'], $row['total_grade']])
            ->values();
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
                    'user'            => $student,
                    'attendance_pct'  => $attendancePct,
                    'total_grade'     => $totalGrade,
                    'letter'          => GradeCategory::letterGrade($totalGrade),
                    'letter_ar'       => GradeCategory::letterGradeAr($totalGrade),
                    'color'           => GradeCategory::gradeColor($totalGrade),
                    'eligible'        => false,
                    'attendance_pass' => null,
                    'grade_pass'      => null,
                    'failure_reason'  => null,
                ];
            })
            ->sortByDesc('total_grade')
            ->values();
    }
}

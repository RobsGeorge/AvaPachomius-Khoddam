<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseGraduationStudent;
use App\Models\User;

/**
 * F-02 — aggregates a student's learning record across every enrolled course:
 * grades (once announced), attendance, graduation status, and the certificate.
 * Pure composition over existing models/routes — no grade math is re-derived here.
 */
class MyLearningService
{
    public function __construct(private StudentRosterService $roster) {}

    /**
     * One card per enrolled course, newest year first.
     *
     * @return list<array<string,mixed>>
     */
    public function courseCards(User $user): array
    {
        return $this->roster->studentEnrolledCourses($user)
            ->sortByDesc('year')
            ->map(fn (Course $course) => $this->cardFor($user, $course))
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function cardFor(User $user, Course $course): array
    {
        $announced = $course->areGradesAnnounced();
        $record = null;

        if ($announced) {
            $graduation = $course->latestGraduation()->first();
            if ($graduation) {
                $record = CourseGraduationStudent::query()
                    ->where('course_graduation_id', $graduation->id)
                    ->where('user_id', $user->user_id)
                    ->with('certificate')
                    ->first();
            }
        }

        $certificateUuid = $record?->certificate?->certificate_uuid;

        return [
            'course' => $course,
            'grades_announced' => $announced,
            'has_record' => $record !== null,
            'final_grade' => $record?->final_total_grade,
            'letter_grade' => $record?->letter_grade,
            'attendance_pct' => $record?->attendance_pct,
            'graduated' => (bool) ($record?->graduated ?? false),
            'grades_url' => $record !== null ? route('courses.final-grades', $course->course_id) : null,
            'attendance_url' => route('attendance.user', $user->user_id),
            'certificate_url' => $certificateUuid ? route('certificates.download', $certificateUuid) : null,
        ];
    }
}

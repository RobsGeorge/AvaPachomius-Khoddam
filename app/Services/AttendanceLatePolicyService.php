<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\Session;
use App\Models\StudentGrade;
use Carbon\Carbon;

class AttendanceLatePolicyService
{
    /**
     * @return array{late_marked: int, grades_synced: int}
     */
    public function applyOnSessionClose(Session $session, int $closedByUserId): array
    {
        $policy = AttendancePolicy::current();

        $lateMarked = $policy->is_enabled
            ? $this->markLateAttendees($session, $policy)
            : 0;

        return [
            'late_marked' => $lateMarked,
            'grades_synced' => $this->syncAttendanceGrades($session, $policy, $closedByUserId),
        ];
    }

    public function sessionStartAt(Session $session, ?AttendancePolicy $policy = null): Carbon
    {
        $timezone = $this->attendanceTimezone();

        $date = $session->session_date?->format('Y-m-d');
        if (! $date) {
            return Carbon::now($timezone)->startOfDay();
        }

        $startTime = $session->session_start_time;
        if ($startTime instanceof \DateTimeInterface) {
            $startTime = $startTime->format('H:i:s');
        } elseif (! is_string($startTime) || $startTime === '') {
            $session->loadMissing('course');
            $startTime = $session->course?->effectiveDefaultSessionStartTime()
                ?? config('attendance.default_session_start_time', '09:00:00');
        } elseif (strlen($startTime) === 5) {
            $startTime .= ':00';
        }

        return Carbon::parse($date.' '.$startTime, $timezone);
    }

    public function lateDeadlineAt(Session $session, ?AttendancePolicy $policy = null): Carbon
    {
        $policy ??= AttendancePolicy::current();

        return $this->sessionStartAt($session, $policy)
            ->copy()
            ->addMinutes($policy->late_threshold_minutes);
    }

    public function isLateAttendance(Attendance $attendance, Session $session, ?AttendancePolicy $policy = null): bool
    {
        if (! $attendance->attendance_time) {
            return false;
        }

        $policy ??= AttendancePolicy::current();
        $timezone = $this->attendanceTimezone();
        $recordedAt = $attendance->attendance_time->copy()->timezone($timezone);

        return $recordedAt->gt($this->lateDeadlineAt($session, $policy));
    }

    private function attendanceTimezone(): string
    {
        return config('attendance.timezone', 'Africa/Cairo');
    }

    private function markLateAttendees(Session $session, AttendancePolicy $policy): int
    {
        $lateMarked = 0;

        Attendance::where('session_id', $session->session_id)
            ->where('status', 'Present')
            ->each(function (Attendance $attendance) use ($session, $policy, &$lateMarked) {
                if ($this->isLateAttendance($attendance, $session, $policy)) {
                    $attendance->update(['status' => 'Late']);
                    $lateMarked++;
                }
            });

        return $lateMarked;
    }

    private function syncAttendanceGrades(Session $session, AttendancePolicy $policy, int $closedByUserId): int
    {
        if (! $session->course_id) {
            return 0;
        }

        $categories = GradeCategory::where('course_id', $session->course_id)
            ->where('type', 'attendance')
            ->get();

        if ($categories->isEmpty()) {
            return 0;
        }

        $attendances = Attendance::where('session_id', $session->session_id)->get();
        if ($attendances->isEmpty()) {
            return 0;
        }

        $synced = 0;
        $now = now();

        foreach ($categories as $category) {
            $item = GradeItem::firstOrCreate(
                [
                    'session_id' => $session->session_id,
                    'category_id' => $category->category_id,
                ],
                [
                    'title' => $session->session_title,
                    'max_score' => 100,
                    'item_date' => $session->session_date,
                    'ordering' => (int) GradeItem::where('category_id', $category->category_id)->max('ordering') + 1,
                ]
            );

            foreach ($attendances as $attendance) {
                $score = $this->scoreForStatus($attendance->status, (float) $item->max_score, $policy);

                StudentGrade::updateOrCreate(
                    [
                        'item_id' => $item->item_id,
                        'user_id' => $attendance->user_id,
                    ],
                    [
                        'score' => $score,
                        'graded_by_id' => $closedByUserId,
                        'graded_at' => $now,
                        'notes' => $attendance->status === 'Late' ? 'Late' : null,
                    ]
                );

                $synced++;
            }
        }

        return $synced;
    }

    public function scoreForStatus(string $status, float $maxScore, ?AttendancePolicy $policy = null): float
    {
        $policy ??= AttendancePolicy::current();

        return match ($status) {
            'Present', 'Permission' => round($maxScore, 2),
            'Late' => round($maxScore * ($policy->late_grade_percentage / 100), 2),
            default => 0.0,
        };
    }
}

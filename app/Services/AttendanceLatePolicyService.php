<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\Lecture;
use App\Models\Session;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Whole-session meetings: each Present row is late when its own attendance_time
     * is after the session start plus the grace period.
     *
     * Per-lecture meetings share that same start. Lateness is the student's arrival,
     * not the moment a later lecture was marked. The earliest Present mark in the
     * meeting decides: on time keeps every Present lecture on time; after the grace
     * period flips every Present lecture to Late. Absent, Permission, and a lecture
     * already marked Late are left as recorded.
     */
    private function markLateAttendees(Session $session, AttendancePolicy $policy): int
    {
        if ($session->usesLectureAttendance()) {
            return $this->markLateByMeetingArrival($session, $policy);
        }

        $lateMarked = 0;

        Attendance::where('session_id', $session->session_id)
            ->where('status', 'Present')
            ->when(
                Schema::hasColumn('attendance', 'lecture_id'),
                fn ($query) => $query->whereNull('lecture_id')
            )
            ->each(function (Attendance $attendance) use ($session, $policy, &$lateMarked) {
                if ($this->isLateAttendance($attendance, $session, $policy)) {
                    $attendance->update(['status' => 'Late']);
                    $lateMarked++;
                }
            });

        return $lateMarked;
    }

    private function markLateByMeetingArrival(Session $session, AttendancePolicy $policy): int
    {
        $rows = Attendance::where('session_id', $session->session_id)
            ->when(
                Schema::hasColumn('attendance', 'lecture_id'),
                fn ($query) => $query->whereNotNull('lecture_id')
            )
            ->get()
            ->groupBy(fn (Attendance $attendance) => $attendance->person_id
                ? 'person:'.$attendance->person_id
                : 'user:'.$attendance->user_id);

        $lateMarked = 0;

        foreach ($rows as $group) {
            $present = $group
                ->filter(fn (Attendance $attendance) => $attendance->status === 'Present' && $attendance->attendance_time)
                ->sortBy(fn (Attendance $attendance) => $attendance->attendance_time->getTimestamp())
                ->values();

            if ($present->isEmpty()) {
                continue;
            }

            if (! $this->isLateAttendance($present->first(), $session, $policy)) {
                continue;
            }

            foreach ($present as $attendance) {
                $attendance->update(['status' => 'Late']);
                $lateMarked++;
            }
        }

        return $lateMarked;
    }

    /**
     * Re-apply attendance gradebook score for one record after a post-close edit.
     * No-ops while the session is still open (grades are first created at close).
     */
    public function syncAttendanceGradeForRecord(Session $session, Attendance $attendance, int $actorId): int
    {
        $session->refresh();

        if (! $session->isAttendanceClosed()) {
            return 0;
        }

        if (! $attendance->user_id || ! $session->course_id) {
            return 0;
        }

        $policy = AttendancePolicy::current();

        return $this->writeAttendanceGrades(
            $session,
            $policy,
            $actorId,
            collect([$attendance]),
        );
    }

    private function syncAttendanceGrades(Session $session, AttendancePolicy $policy, int $closedByUserId): int
    {
        if (! $session->course_id) {
            return 0;
        }

        $attendances = Attendance::where('session_id', $session->session_id)->get();
        if ($attendances->isEmpty()) {
            return 0;
        }

        return $this->writeAttendanceGrades($session, $policy, $closedByUserId, $attendances);
    }

    /**
     * @param  Collection<int, Attendance>  $attendances
     */
    private function writeAttendanceGrades(
        Session $session,
        AttendancePolicy $policy,
        int $actorId,
        $attendances,
    ): int {
        if ($session->usesLectureAttendance() && Schema::hasColumn('grade_items', 'lecture_id')) {
            $synced = 0;
            foreach ($attendances->groupBy('lecture_id') as $lectureId => $rows) {
                if (! $lectureId) {
                    continue;
                }
                $lecture = Lecture::find($lectureId);
                if (! $lecture) {
                    continue;
                }
                $synced += $this->writeItemGrades($session, $policy, $actorId, $rows, $lecture);
            }

            return $synced;
        }

        $rows = Schema::hasColumn('attendance', 'lecture_id')
            ? $attendances->filter(fn (Attendance $attendance) => $attendance->lecture_id === null)->values()
            : $attendances;

        return $this->writeItemGrades($session, $policy, $actorId, $rows, null);
    }

    /**
     * One gradebook line per occasion: the session, or each lecture.
     *
     * @param  Collection<int, Attendance>  $attendances
     */
    private function writeItemGrades(
        Session $session,
        AttendancePolicy $policy,
        int $actorId,
        $attendances,
        ?Lecture $lecture,
    ): int {
        $categories = GradeCategory::where('course_id', $session->course_id)
            ->where('type', 'attendance')
            ->get();

        if ($categories->isEmpty()) {
            return 0;
        }

        $synced = 0;
        $now = now();
        $hasLectureColumn = Schema::hasColumn('grade_items', 'lecture_id');

        foreach ($categories as $category) {
            $lookup = [
                'session_id' => $session->session_id,
                'category_id' => $category->category_id,
            ];
            if ($hasLectureColumn) {
                $lookup['lecture_id'] = $lecture?->lecture_id;
            }

            $item = GradeItem::firstOrCreate(
                $lookup,
                [
                    'title' => $lecture?->title ?: $session->session_title,
                    'max_score' => 100,
                    'item_date' => $lecture?->lecture_date ?? $session->session_date,
                    'ordering' => (int) GradeItem::where('category_id', $category->category_id)->max('ordering') + 1,
                ]
            );

            foreach ($attendances as $attendance) {
                if (! $attendance->user_id) {
                    continue;
                }

                $score = $this->scoreForStatus($attendance->status, (float) $item->max_score, $policy);

                StudentGrade::updateOrCreate(
                    [
                        'item_id' => $item->item_id,
                        'user_id' => $attendance->user_id,
                    ],
                    [
                        'score' => $score,
                        'graded_by_id' => $actorId,
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

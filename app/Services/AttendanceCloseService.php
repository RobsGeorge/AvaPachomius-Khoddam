<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Role;
use App\Models\Session;
use App\Models\UserCourseRole;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceCloseService
{
    public function __construct(
        private AttendanceLatePolicyService $latePolicy,
    ) {}

    public function attendanceTimezone(): string
    {
        return config('attendance.timezone', 'Africa/Cairo');
    }

    public function todayInTimezone(): Carbon
    {
        return Carbon::now($this->attendanceTimezone())->startOfDay();
    }

    /**
     * @return array{absent_marked: int, late_marked: int, grades_synced: int, already_closed: bool}
     */
    public function closeSession(Session $session, int $closedByUserId): array
    {
        $session->refresh();

        if ($session->isAttendanceClosed()) {
            return [
                'absent_marked' => 0,
                'late_marked' => 0,
                'grades_synced' => 0,
                'already_closed' => true,
            ];
        }

        $absentMarked = 0;
        $lateMarked = 0;
        $gradesSynced = 0;

        DB::transaction(function () use ($session, $closedByUserId, &$absentMarked, &$lateMarked, &$gradesSynced) {
            $enrolledStudentIds = $this->enrolledStudentIdsForCourse($session->course_id);

            if ($enrolledStudentIds->isNotEmpty()) {
                $existingUserIds = Attendance::where('session_id', $session->session_id)
                    ->pluck('user_id')
                    ->all();

                $missingStudentIds = $enrolledStudentIds
                    ->diff($existingUserIds)
                    ->values();

                $now = now();
                $records = $missingStudentIds->map(fn ($userId) => [
                    'user_id' => $userId,
                    'session_id' => $session->session_id,
                    'taken_by_id' => $closedByUserId,
                    'status' => 'Absent',
                    'attendance_time' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                foreach (array_chunk($records, 100) as $chunk) {
                    Attendance::insert($chunk);
                }

                $absentMarked = count($records);
            }

            $lateResult = $this->latePolicy->applyOnSessionClose($session, $closedByUserId);
            $lateMarked = $lateResult['late_marked'];
            $gradesSynced = $lateResult['grades_synced'];

            $session->update([
                'attendance_closed_at' => now(),
                'attendance_closed_by_id' => $closedByUserId,
            ]);
        });

        return [
            'absent_marked' => $absentMarked,
            'late_marked' => $lateMarked,
            'grades_synced' => $gradesSynced,
            'already_closed' => false,
        ];
    }

    /**
     * Close all open sessions for a given calendar date (Y-m-d in attendance timezone).
     */
    public function closeSessionsForDate(Carbon $date, int $closedByUserId): int
    {
        $dateString = $date->toDateString();

        $sessions = Session::whereDate('session_date', $dateString)
            ->whereNull('attendance_closed_at')
            ->get();

        $totalAbsent = 0;

        foreach ($sessions as $session) {
            $result = $this->closeSession($session, $closedByUserId);

            if (! $result['already_closed']) {
                $totalAbsent += $result['absent_marked'];
            }
        }

        return $totalAbsent;
    }

    /** @return Collection<int, int> */
    private function enrolledStudentIdsForCourse(?int $courseId): Collection
    {
        if (! $courseId) {
            return collect();
        }

        $studentRoleId = Role::where('role_name', 'Student')->value('role_id');

        return UserCourseRole::where('course_id', $courseId)
            ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
            ->pluck('user_id')
            ->unique()
            ->values();
    }
}

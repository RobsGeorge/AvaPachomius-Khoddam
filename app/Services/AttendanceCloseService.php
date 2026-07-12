<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Models\UserCourseRole;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCloseService
{
    public const STATUSES = ['Present', 'Absent', 'Late', 'Permission'];

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
            $absentMarked = $this->fillMissingRecords($session, $closedByUserId, 'Absent');

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

    public function fillMissingRecords(Session $session, int $actorId, string $defaultStatus = 'Absent'): int
    {
        $this->assertValidStatus($defaultStatus);

        $session->refresh();

        return $this->insertMissingRecords($session, $actorId, $defaultStatus);
    }

    public function createOrUpdateRecord(
        Session $session,
        int $userId,
        string $status,
        int $actorId,
        ?string $permissionReason = null,
        bool $allowNonEnrolled = false,
    ): Attendance {
        $this->assertValidStatus($status);

        if ($status === 'Permission' && blank($permissionReason)) {
            throw ValidationException::withMessages([
                'permission_reason' => __('pages.enter_permission_reason'),
            ]);
        }

        $user = User::find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => __('pages.student_not_found'),
            ]);
        }

        $this->assertStudentCanBeRecorded($session, $user, $allowNonEnrolled);

        $now = now();
        $attributes = [
            'status' => $status,
            'taken_by_id' => $actorId,
            'attendance_time' => $now,
            'permission_reason' => $status === 'Permission' ? $permissionReason : null,
        ];

        $attendance = Attendance::updateOrCreate(
            [
                'session_id' => $session->session_id,
                'user_id' => $userId,
            ],
            $attributes,
        );

        return $attendance->fresh(['user', 'takenBy', 'session']);
    }

    /** @return Collection<int, int> */
    public function enrolledStudentIdsForCourse(?int $courseId): Collection
    {
        if (! $courseId) {
            return collect();
        }

        $studentRoleIds = Role::studentRoleIds();

        return UserCourseRole::where('course_id', $courseId)
            ->when($studentRoleIds->isNotEmpty(), fn ($q) => $q->whereIn('role_id', $studentRoleIds))
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /** @return Collection<int, User> */
    public function enrolledStudentsForSession(Session $session): Collection
    {
        $studentIds = $this->enrolledStudentIdsForCourse($session->course_id);

        if ($studentIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->orderBy('second_name')
            ->get();
    }

    /**
     * @return array{
     *     enrolled: int,
     *     recorded: int,
     *     missing: int,
     *     rows: list<array{user: User, attendance: ?Attendance, missing: bool}>
     * }
     */
    public function sessionRoster(Session $session): array
    {
        $session->refresh()->loadMissing('course');

        $students = $this->enrolledStudentsForSession($session);
        $records = Attendance::with(['user', 'takenBy'])
            ->where('session_id', $session->session_id)
            ->get()
            ->keyBy('user_id');

        $rows = [];

        foreach ($students as $student) {
            $attendance = $records->get($student->user_id);
            $rows[] = [
                'user' => $student,
                'attendance' => $attendance,
                'missing' => $attendance === null,
            ];
        }

        $enrolled = count($rows);
        $recorded = collect($rows)->where('missing', false)->count();

        return [
            'enrolled' => $enrolled,
            'recorded' => $recorded,
            'missing' => max(0, $enrolled - $recorded),
            'rows' => $rows,
        ];
    }

    public function missingRecordCount(Session $session): int
    {
        $enrolled = $this->enrolledStudentIdsForCourse($session->course_id)->count();
        $recorded = Attendance::where('session_id', $session->session_id)->count();

        return max(0, $enrolled - $recorded);
    }

    public function isStudentEnrolledInCourse(int $userId, ?int $courseId): bool
    {
        if (! $courseId) {
            return false;
        }

        return $this->enrolledStudentIdsForCourse($courseId)->contains($userId);
    }

    /** @return Collection<int, User> */
    public function searchStudentsForSession(Session $session, string $query, bool $includeNonEnrolled = false): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $studentRoleIds = Role::studentRoleIds();
        $like = '%'.$query.'%';

        $usersQuery = User::query()
            ->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('second_name', 'like', $like)
                    ->orWhere('third_name', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like)
                    ->orWhere('national_id', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->limit(20);

        if ($studentRoleIds->isNotEmpty()) {
            $usersQuery->whereIn('user_id', function ($sub) use ($studentRoleIds) {
                $sub->select('user_id')
                    ->from('user_course_role')
                    ->whereIn('role_id', $studentRoleIds);
            });
        }

        if (! $includeNonEnrolled && $session->course_id) {
            $enrolledIds = $this->enrolledStudentIdsForCourse($session->course_id);
            $usersQuery->whereIn('user_id', $enrolledIds);
        }

        return $usersQuery->orderBy('first_name')->orderBy('second_name')->get();
    }

    private function insertMissingRecords(Session $session, int $actorId, string $status): int
    {
        $enrolledStudentIds = $this->enrolledStudentIdsForCourse($session->course_id);

        if ($enrolledStudentIds->isEmpty()) {
            return 0;
        }

        $existingUserIds = Attendance::where('session_id', $session->session_id)
            ->pluck('user_id')
            ->all();

        $missingStudentIds = $enrolledStudentIds
            ->diff($existingUserIds)
            ->values();

        if ($missingStudentIds->isEmpty()) {
            return 0;
        }

        $now = now();
        $records = $missingStudentIds->map(fn ($userId) => [
            'user_id' => $userId,
            'session_id' => $session->session_id,
            'taken_by_id' => $actorId,
            'status' => $status,
            'attendance_time' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($records, 100) as $chunk) {
            Attendance::insert($chunk);
        }

        return count($records);
    }

    private function assertStudentCanBeRecorded(Session $session, User $user, bool $allowNonEnrolled): void
    {
        $studentRoleIds = Role::studentRoleIds();

        if ($studentRoleIds->isNotEmpty()) {
            $hasStudentRole = UserCourseRole::where('user_id', $user->user_id)
                ->whereIn('role_id', $studentRoleIds)
                ->exists();

            if (! $hasStudentRole) {
                throw ValidationException::withMessages([
                    'user_id' => __('pages.attendance_not_a_student'),
                ]);
            }
        }

        if (! $allowNonEnrolled && ! $this->isStudentEnrolledInCourse($user->user_id, $session->course_id)) {
            throw ValidationException::withMessages([
                'user_id' => __('pages.attendance_student_not_in_course'),
            ]);
        }
    }

    private function assertValidStatus(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => __('pages.attendance_invalid_status'),
            ]);
        }
    }
}

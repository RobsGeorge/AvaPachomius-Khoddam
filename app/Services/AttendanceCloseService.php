<?php

namespace App\Services;

use App\Exceptions\OptimisticLockException;
use App\Models\Attendance;
use App\Models\Church;
use App\Models\Lecture;
use App\Models\Person;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function fillMissingRecords(
        Session $session,
        int $actorId,
        string $defaultStatus = 'Absent',
        ?int $lectureId = null,
    ): int {
        $this->assertValidStatus($defaultStatus);

        $session->refresh();

        return $this->insertMissingRecords($session, $actorId, $defaultStatus, $lectureId);
    }

    public function createOrUpdateRecord(
        Session $session,
        int $userId,
        string $status,
        int $actorId,
        ?string $permissionReason = null,
        bool $allowNonEnrolled = false,
        ?int $lectureId = null,
        ?int $expectedLockVersion = null,
    ): Attendance {
        $this->assertValidStatus($status);
        $this->assertPermissionReason($status, $permissionReason);

        $user = User::find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => __('pages.student_not_found'),
            ]);
        }

        $this->assertStudentCanBeRecorded($session, $user, $allowNonEnrolled);

        $resolvedLectureId = $this->resolvedLectureId($session, $lectureId);
        $identity = [
            'session_id' => $session->session_id,
            'user_id' => $userId,
        ];
        if (Schema::hasColumn('attendance', 'lecture_id')) {
            $identity['lecture_id'] = $resolvedLectureId;
        }

        $attributes = $this->statusAttributes($status, $actorId, $permissionReason);
        if (Schema::hasColumn('attendance', 'person_id') && $user->person_id) {
            $attributes['person_id'] = $user->person_id;
        }

        $attendance = $this->persistAttendance($identity, $attributes, $expectedLockVersion);

        $fresh = $attendance->fresh(['user', 'takenBy', 'session', 'lecture']);
        $this->latePolicy->syncAttendanceGradeForRecord($session, $fresh, $actorId);

        return $fresh;
    }

    /**
     * Mark a person who may have no user account (rung-0). Grade sync runs when a user is linked.
     */
    public function createOrUpdateForPerson(
        Session $session,
        int $personId,
        string $status,
        int $actorId,
        ?string $permissionReason = null,
        bool $allowNonEnrolled = false,
        ?int $lectureId = null,
    ): Attendance {
        $this->assertValidStatus($status);
        $this->assertPermissionReason($status, $permissionReason);

        $person = Person::withoutTenancy()->find($personId);
        if (! $person) {
            throw ValidationException::withMessages([
                'person_id' => __('pages.student_not_found'),
            ]);
        }

        $user = User::query()->where('person_id', $person->person_id)->orderBy('user_id')->first();
        if ($user) {
            $this->assertStudentCanBeRecorded($session, $user, $allowNonEnrolled);
        }

        $resolvedLectureId = $this->resolvedLectureId($session, $lectureId);
        $identity = [
            'session_id' => $session->session_id,
            'person_id' => $person->person_id,
        ];
        if (Schema::hasColumn('attendance', 'lecture_id')) {
            $identity['lecture_id'] = $resolvedLectureId;
        }

        $attributes = $this->statusAttributes($status, $actorId, $permissionReason);
        $attributes['user_id'] = $user?->user_id;

        $attendance = $this->persistAttendance($identity, $attributes, null);
        $fresh = $attendance->fresh(['user', 'takenBy', 'session', 'lecture']);
        $this->latePolicy->syncAttendanceGradeForRecord($session, $fresh, $actorId);

        return $fresh;
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
     *     rows: list<array{user: User, attendance: ?Attendance, missing: bool}>,
     *     needs_lectures: bool,
     *     lecture: ?Lecture,
     *     lectures: Collection<int, Lecture>
     * }
     */
    public function sessionRoster(Session $session, ?int $lectureId = null): array
    {
        $session->refresh()->loadMissing(['course', 'lectures']);

        $lecture = $this->rosterLecture($session, $lectureId);
        $students = $this->enrolledStudentsForSession($session);
        $records = $this->recordsForRoster($session, $lecture)->keyBy('user_id');

        $rows = [];
        $needsLectures = $session->usesLectureAttendance() && $session->lectures->isEmpty();

        if (! $needsLectures) {
            foreach ($students as $student) {
                $attendance = $records->get($student->user_id);
                $rows[] = [
                    'user' => $student,
                    'attendance' => $attendance,
                    'missing' => $attendance === null,
                ];
            }
        }

        $enrolled = count($rows);
        $recorded = collect($rows)->where('missing', false)->count();

        return [
            'enrolled' => $enrolled,
            'recorded' => $recorded,
            'missing' => max(0, $enrolled - $recorded),
            'rows' => $rows,
            'needs_lectures' => $needsLectures,
            'lecture' => $lecture,
            'lectures' => $session->lectures,
        ];
    }

    public function missingRecordCount(Session $session): int
    {
        $session->loadMissing('lectures');
        $enrolled = $this->enrolledStudentIdsForCourse($session->course_id);

        if ($session->usesLectureAttendance()) {
            if ($session->lectures->isEmpty()) {
                return 0;
            }

            $missing = 0;
            foreach ($session->lectures as $lecture) {
                $recorded = $this->recordedUserIds($session, $lecture->lecture_id);
                $missing += $enrolled->diff($recorded)->count();
            }

            return $missing;
        }

        $recorded = $this->recordedUserIds($session, null);

        return max(0, $enrolled->diff($recorded)->count());
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

    private function insertMissingRecords(Session $session, int $actorId, string $status, ?int $lectureId = null): int
    {
        $session->loadMissing('lectures');

        if ($session->usesLectureAttendance()) {
            $lectures = $lectureId
                ? $session->lectures->where('lecture_id', $lectureId)
                : $session->lectures;

            $count = 0;
            foreach ($lectures as $lecture) {
                $count += $this->insertMissingForScope($session, $actorId, $status, (int) $lecture->lecture_id);
            }

            return $count;
        }

        return $this->insertMissingForScope($session, $actorId, $status, null);
    }

    private function insertMissingForScope(Session $session, int $actorId, string $status, ?int $lectureId): int
    {
        $enrolledStudentIds = $this->enrolledStudentIdsForCourse($session->course_id);

        if ($enrolledStudentIds->isEmpty()) {
            return 0;
        }

        $existingUserIds = $this->recordedUserIds($session, $lectureId)->all();

        $missingStudentIds = $enrolledStudentIds
            ->diff($existingUserIds)
            ->values();

        if ($missingStudentIds->isEmpty()) {
            return 0;
        }

        // Attendance::insert() bypasses BelongsToChurch creating stamps. After T7,
        // attendance.church_id is NOT NULL on MySQL — omit it and close-attendance 500s.
        $churchId = $this->churchIdForBulkAttendanceInsert($session);
        $now = now();
        $records = $missingStudentIds->map(function ($userId) use ($session, $actorId, $status, $now, $churchId, $lectureId) {
            $row = [
                'user_id' => $userId,
                'session_id' => $session->session_id,
                'taken_by_id' => $actorId,
                'status' => $status,
                'attendance_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('attendance', 'lecture_id')) {
                $row['lecture_id'] = $lectureId;
            }
            if ($churchId !== null) {
                $row['church_id'] = $churchId;
            }
            if (Schema::hasColumn('attendance', 'lock_version')) {
                $row['lock_version'] = 0;
            }

            return $row;
        })->all();

        foreach (array_chunk($records, 100) as $chunk) {
            Attendance::insert($chunk);
        }

        return count($records);
    }

    /**
     * Resolve church_id for bulk attendance rows (insert skips Eloquent events).
     */
    private function churchIdForBulkAttendanceInsert(Session $session): ?int
    {
        if (! Schema::hasColumn('attendance', 'church_id')) {
            return null;
        }

        $session->loadMissing('course');

        $churchId = $session->church_id
            ?? $session->course?->church_id
            ?? app(TenantContext::class)->churchId()
            ?? TenantContext::id();

        if ($churchId !== null) {
            return (int) $churchId;
        }

        if (! Schema::hasTable('church')) {
            return null;
        }

        $mainId = Church::query()->where('slug', config('tenancy.main_slug'))->value('church_id');

        return $mainId !== null ? (int) $mainId : null;
    }

    private function assertPermissionReason(string $status, ?string $permissionReason): void
    {
        if ($status === 'Permission' && blank($permissionReason)) {
            throw ValidationException::withMessages([
                'permission_reason' => __('pages.enter_permission_reason'),
            ]);
        }
    }

    /**
     * Session grain stores a null lecture_id. Lecture grain requires a lecture on this session.
     */
    private function resolvedLectureId(Session $session, ?int $lectureId): ?int
    {
        if (! Schema::hasColumn('attendance', 'lecture_id') || ! $session->usesLectureAttendance()) {
            return null;
        }

        if (! $lectureId) {
            throw ValidationException::withMessages([
                'lecture_id' => __('pages.attendance_lecture_required'),
            ]);
        }

        $session->loadMissing('lectures');
        $match = $session->lectures->first(fn (Lecture $lecture) => (int) $lecture->lecture_id === $lectureId);

        if (! $match) {
            throw ValidationException::withMessages([
                'lecture_id' => __('pages.attendance_lecture_not_in_session'),
            ]);
        }

        return (int) $match->lecture_id;
    }

    /** @return array{status: string, taken_by_id: int, attendance_time: \Illuminate\Support\Carbon, permission_reason: ?string} */
    private function statusAttributes(string $status, int $actorId, ?string $permissionReason): array
    {
        return [
            'status' => $status,
            'taken_by_id' => $actorId,
            'attendance_time' => now(),
            'permission_reason' => $status === 'Permission' ? $permissionReason : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $attributes
     */
    private function persistAttendance(array $identity, array $attributes, ?int $expectedLockVersion): Attendance
    {
        $query = Attendance::query();
        foreach ($identity as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        $existing = $query->first();

        if ($existing && Schema::hasColumn('attendance', 'lock_version') && $expectedLockVersion !== null) {
            if ((int) $existing->lock_version !== $expectedLockVersion) {
                throw new OptimisticLockException(
                    __('structure.optimistic_lock_conflict'),
                    (int) $existing->lock_version
                );
            }
        }

        if (Schema::hasColumn('attendance', 'lock_version')) {
            $attributes['lock_version'] = $existing ? ((int) $existing->lock_version + 1) : 0;
        }

        return Attendance::updateOrCreate($identity, $attributes);
    }

    private function rosterLecture(Session $session, ?int $lectureId): ?Lecture
    {
        if (! $session->usesLectureAttendance()) {
            return null;
        }

        if ($session->lectures->isEmpty()) {
            return null;
        }

        if ($lectureId) {
            return $session->lectures->first(fn (Lecture $lecture) => (int) $lecture->lecture_id === $lectureId);
        }

        return $session->lectures->first();
    }

    /** @return Collection<int, Attendance> */
    private function recordsForRoster(Session $session, ?Lecture $lecture): Collection
    {
        $query = Attendance::with(['user', 'takenBy'])
            ->where('session_id', $session->session_id);

        if (! Schema::hasColumn('attendance', 'lecture_id')) {
            return $query->get();
        }

        if ($lecture) {
            return $query->where('lecture_id', $lecture->lecture_id)->get();
        }

        return $query->whereNull('lecture_id')->get();
    }

    /** @return Collection<int, int> */
    private function recordedUserIds(Session $session, ?int $lectureId): Collection
    {
        $query = Attendance::query()->where('session_id', $session->session_id);

        if (Schema::hasColumn('attendance', 'lecture_id')) {
            if ($lectureId) {
                $query->where('lecture_id', $lectureId);
            } else {
                $query->whereNull('lecture_id');
            }
        }

        return $query->whereNotNull('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();
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

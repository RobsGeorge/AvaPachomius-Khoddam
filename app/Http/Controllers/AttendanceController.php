<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Course;
use App\Models\Role;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Models\Session;
use App\Models\Attendance;
use App\Models\Module;
use App\Services\AttendanceCloseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    private const SESSION_DATE_SQL = 'DATE(DATE_ADD(session.session_date, INTERVAL 3 HOUR))';

    private const SESSION_MONTH_SQL = 'DATE_FORMAT(DATE_ADD(session.session_date, INTERVAL 3 HOUR), "%Y-%m")';

    private function sessionDateSql(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'DATE(session.session_date)';
        }

        return self::SESSION_DATE_SQL;
    }

    private function sessionMonthSql(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'strftime("%Y-%m", session.session_date)';
        }

        return self::SESSION_MONTH_SQL;
    }

    public function __construct(
        private AttendanceCloseService $attendanceClose,
    ) {}

    private function studentRoleIds(): Collection
    {
        return Role::studentRoleIds();
    }

    /** Limit attendance queries to enrolled students when the Student role exists. */
    private function scopeToStudents(Builder $query, string $userIdColumn = 'attendance.user_id'): Builder
    {
        $studentRoleIds = $this->studentRoleIds();

        if ($studentRoleIds->isEmpty()) {
            return $query;
        }

        if (! Schema::hasTable('user_course_role')) {
            return $query;
        }

        return $query->whereIn($userIdColumn, function ($sub) use ($studentRoleIds) {
            $sub->select('user_id')
                ->from('user_course_role')
                ->whereIn('role_id', $studentRoleIds);
        });
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function enrolledStudentIds()
    {
        $studentRoleIds = $this->studentRoleIds();

        return DB::table('user_course_role')
            ->when($studentRoleIds->isNotEmpty(), fn ($q) => $q->whereIn('role_id', $studentRoleIds))
            ->distinct()
            ->pluck('user_id');
    }

    private function attendanceRecordsForUser(int|string $userId)
    {
        return Attendance::with(['session', 'takenBy'])
            ->where('user_id', $userId)
            ->orderByDesc('attendance_time')
            ->get();
    }

    /** QR scan landing: today's session(s) + student confirmation. */
    public function showTodaySessions(Request $request)
    {
        $userId = $request->query('user_id');

        if (! $userId || ! User::find($userId)) {
            abort(404, __('pages.student_not_found'));
        }

        $student = User::find($userId);
        $today = now()->toDateString();

        $sessions = $this->scopeSessionsToCurrentCourse(Session::with('course'))
            ->whereDate('session_date', $today)
            ->orderBy('session_title')
            ->get();

        $existingAttendance = collect();
        if ($sessions->isNotEmpty()) {
            $existingAttendance = Attendance::with('takenBy')
                ->where('user_id', $userId)
                ->whereIn('session_id', $sessions->pluck('session_id'))
                ->get()
                ->keyBy('session_id');
        }

        return view('attendance.sessions', [
            'user' => $student,
            'userId' => $userId,
            'today' => $today,
            'sessions' => $sessions,
            'existingAttendance' => $existingAttendance,
        ]);
    }

    public function recordAttendance(Request $request, Session $session)
    {
        $studentUserId = $request->input('student_user_id');

        if (! $studentUserId || ! User::find($studentUserId)) {
            return redirect()->back()->with('error', __('pages.student_not_found'));
        }

        if (! $session->session_date || $session->session_date->toDateString() !== now()->toDateString()) {
            return redirect()->back()->with('error', __('pages.attendance_not_today_session'));
        }

        if ($session->isAttendanceClosed()) {
            return redirect()->back()->with('error', __('pages.attendance_session_closed'));
        }

        $exists = Attendance::where('session_id', $session->session_id)
            ->where('user_id', $studentUserId)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('warning', __('pages.attendance_already_recorded'));
        }

        try {
            Attendance::create([
                'session_id' => $session->session_id,
                'user_id' => $studentUserId,
                'taken_by_id' => auth()->user()->user_id,
                'status' => 'Present',
                'attendance_time' => now(),
            ]);
        } catch (QueryException $e) {
            report($e);

            return redirect()->back()->with('error', __('pages.attendance_record_failed'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', __('pages.attendance_record_failed'));
        }

        return redirect()->back()->with('success', __('pages.attendance_record_success'));
    }

    // View all attendance records (for admin and instructor)
    public function viewAllAttendance(Request $request)
    {
        $filterBy = $this->resolveFilterBy($request);
        $baseQuery = $this->filteredAttendanceQuery($request, $filterBy);
        $perPage = 10;
        $page = max(1, (int) $request->input('page', 1));

        $subgroupByStatus = false;
        $singleSessionReport = false;
        $groups = [];
        $groupPaginator = $this->paginateCollection(collect(), $perPage, $page, $request);

        if ($filterBy === 'session' && $request->filled('session_id')) {
            [$groups, $groupPaginator, $singleSessionReport] = $this->buildSingleSessionReport(
                Session::with(['course', 'attendanceClosedBy'])->findOrFail($request->input('session_id')),
                $baseQuery,
                $request,
            );
            $subgroupByStatus = true;
        } elseif ($filterBy === 'module' && $request->filled('module_id')) {
            if ($request->filled('session_id')) {
                $moduleId = (int) $request->input('module_id');
                $sessionId = (int) $request->input('session_id');

                if (! $this->sessionBelongsToModule($sessionId, $moduleId)) {
                    abort(404);
                }

                [$groups, $groupPaginator, $singleSessionReport] = $this->buildSingleSessionReport(
                    Session::with(['course', 'attendanceClosedBy'])->findOrFail($sessionId),
                    $baseQuery,
                    $request,
                );
            } else {
                [$groups, $groupPaginator] = $this->buildSessionGroups($baseQuery, $request, $perPage, $page);
            }
            $subgroupByStatus = true;
        } elseif ($filterBy === 'date' && $request->filled('session_date')) {
            [$groups, $groupPaginator] = $this->buildSessionGroups($baseQuery, $request, $perPage, $page);
            $subgroupByStatus = true;
        } elseif ($filterBy === 'date') {
            [$groups, $groupPaginator] = $this->buildDateGroups($baseQuery, $request, $perPage, $page);
            $subgroupByStatus = true;
        }

        $sessionOptions = $this->scopeSessionsToCurrentCourse(Session::query())
            ->with('course')
            ->orderByDesc('session_date')
            ->orderBy('session_title')
            ->limit(100)
            ->get();

        $moduleOptions = $this->moduleOptionsWithAttendance();
        $moduleSessionOptions = collect();
        if ($filterBy === 'module' && $request->filled('module_id')) {
            $moduleSessionOptions = $this->sessionOptionsForModule((int) $request->input('module_id'));
        }

        $overallStats = $this->getOverallStatistics();
        $dailyStats = $this->getDailyStatistics();
        $userStats = $this->getUserStatistics();

        $sessionReportSession = null;
        $canManageSessionAttendance = false;
        $sessionReportMissingCount = 0;
        $todayLocal = $this->attendanceClose->todayInTimezone()->toDateString();

        if ($singleSessionReport && $groups !== []) {
            $sessionReportSession = $groups[0]['session'] ?? null;
            if ($sessionReportSession instanceof Session) {
                $sessionReportSession->loadMissing(['attendanceClosedBy', 'course']);
                $canManageSessionAttendance = $this->canManageSessionAttendance(
                    auth()->user(),
                    $sessionReportSession,
                );
                if ($canManageSessionAttendance) {
                    $sessionReportMissingCount = $this->attendanceClose->missingRecordCount($sessionReportSession);
                }
            }
        }

        return view('attendance.all', compact(
            'groups',
            'filterBy',
            'groupPaginator',
            'sessionOptions',
            'moduleOptions',
            'moduleSessionOptions',
            'subgroupByStatus',
            'singleSessionReport',
            'overallStats',
            'dailyStats',
            'userStats',
            'sessionReportSession',
            'canManageSessionAttendance',
            'sessionReportMissingCount',
            'todayLocal',
        ));
    }

    private function canManageSessionAttendance(?User $user, Session $session): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->is_superadmin ?? false) {
            return true;
        }

        if ($session->course_id) {
            return $user->isInstructorOrAdmin((string) $session->course_id);
        }

        return $user->isInstructorOrAdmin();
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: LengthAwarePaginator, 2: bool} */
    private function buildSingleSessionReport(Session $session, Builder $baseQuery, Request $request): array
    {
        $records = $this->recordsForSessionGroup($baseQuery, $session->session_id);
        $roster = $this->attendanceClose->sessionRoster($session);
        $meta = $session->session_date?->format('Y-m-d');
        if ($session->course) {
            $meta = trim(($meta ?? '').' · '.$session->course->title, ' ·');
        }
        $groups = [
            $this->formatGroup((string) $session->session_id, $session->session_title, $records, $meta, $session, $roster),
        ];

        return [
            $groups,
            $this->paginateCollection(collect($groups), 1, 1, $request),
            true,
        ];
    }

    private function resolveFilterBy(Request $request): string
    {
        $filterBy = $request->input('filter_by', 'date');

        return in_array($filterBy, ['date', 'session', 'module'], true) ? $filterBy : 'date';
    }

    private function filteredAttendanceQuery(Request $request, string $filterBy): Builder
    {
        $query = $this->scopeToStudents(Attendance::query());
        $query = $this->scopeAttendanceToCurrentCourse($query);

        if ($filterBy === 'date' && $request->filled('session_date')) {
            $query->whereHas('session', function ($sessionQuery) use ($request) {
                $sessionQuery->whereDate('session_date', $request->input('session_date'));
            });
        }

        if ($filterBy === 'session' && $request->filled('session_id')) {
            $query->where('session_id', $request->input('session_id'));
        }

        if ($filterBy === 'module' && $request->filled('module_id')) {
            $moduleId = (int) $request->input('module_id');

            if ($request->filled('session_id')) {
                $sessionId = (int) $request->input('session_id');
                if ($this->sessionBelongsToModule($sessionId, $moduleId)) {
                    $query->where('session_id', $sessionId);
                } else {
                    $query->whereRaw('0 = 1');
                }
            } else {
                $query->whereIn('session_id', $this->sessionIdsForModule($moduleId));
            }
        }

        return $query;
    }

    private function scopeAttendanceToCurrentCourse(Builder $query): Builder
    {
        $course = current_course();
        if (! $course) {
            return $query;
        }

        return $query->whereHas('session', function ($sessionQuery) use ($course) {
            $sessionQuery->where('course_id', $course->course_id);
        });
    }

    private function scopeSessionsToCurrentCourse(Builder $query): Builder
    {
        $course = current_course();
        if (! $course) {
            return $query;
        }

        return $query->where('course_id', $course->course_id);
    }

    /** @return Collection<int, int> */
    private function sessionIdsForModule(int $moduleId): Collection
    {
        $fromColumn = Session::where('module_id', $moduleId)->pluck('session_id');
        $fromPivot = DB::table('module_session')->where('module_id', $moduleId)->pluck('session_id');

        return $fromColumn->merge($fromPivot)->unique()->values();
    }

    private function sessionBelongsToModule(int $sessionId, int $moduleId): bool
    {
        return $this->sessionIdsForModule($moduleId)->contains($sessionId);
    }

    /** @return Collection<int, Module> */
    private function moduleOptionsWithAttendance(): Collection
    {
        $attendanceSessionIds = $this->scopeToStudents(Attendance::query())->select('session_id');

        $moduleIds = $this->scopeSessionsToCurrentCourse(Session::query())
            ->whereIn('session_id', $attendanceSessionIds)
            ->whereNotNull('module_id')
            ->pluck('module_id')
            ->merge(
                DB::table('module_session')
                    ->whereIn('session_id', $attendanceSessionIds)
                    ->pluck('module_id')
            )
            ->unique()
            ->filter()
            ->values();

        if ($moduleIds->isEmpty()) {
            return collect();
        }

        return Module::whereIn('module_id', $moduleIds)->orderBy('title')->get();
    }

    /** @return Collection<int, Session> */
    private function sessionOptionsForModule(int $moduleId): Collection
    {
        $sessionIds = $this->sessionIdsForModule($moduleId);

        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return Session::query()
            ->with('course')
            ->whereIn('session_id', $sessionIds)
            ->orderByDesc('session_date')
            ->orderBy('session_title')
            ->get();
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: LengthAwarePaginator} */
    private function buildDateGroups(Builder $baseQuery, Request $request, int $perPage, int $page): array
    {
        $dates = Session::query()
            ->whereIn('session_id', (clone $baseQuery)->select('session_id'))
            ->orderByDesc('session_date')
            ->pluck('session_date')
            ->map(fn ($date) => $date->toDateString())
            ->unique()
            ->values();

        $paginator = $this->paginateCollection($dates, $perPage, $page, $request);
        $groups = [];

        foreach ($paginator as $date) {
            $records = $this->recordsForDateGroup($baseQuery, $date);
            $groups[] = $this->formatGroup($date, $date, $records, null);
        }

        return [$groups, $paginator];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: LengthAwarePaginator} */
    private function buildSessionGroups(Builder $baseQuery, Request $request, int $perPage, int $page): array
    {
        $sessions = Session::query()
            ->with('course')
            ->whereIn('session_id', (clone $baseQuery)->select('session_id'))
            ->orderByDesc('session_date')
            ->orderBy('session_title')
            ->get();

        $paginator = $this->paginateCollection($sessions, $perPage, $page, $request);
        $groups = [];

        foreach ($paginator as $session) {
            $records = $this->recordsForSessionGroup($baseQuery, $session->session_id);
            $heading = $session->session_title;
            $meta = $session->session_date?->format('Y-m-d');
            if ($session->course) {
                $meta = trim(($meta ?? '').' · '.$session->course->title, ' ·');
            }
            $groups[] = $this->formatGroup((string) $session->session_id, $heading, $records, $meta);
        }

        return [$groups, $paginator];
    }

    private function recordsForDateGroup(Builder $baseQuery, string $date): Collection
    {
        return (clone $baseQuery)
            ->whereHas('session', fn ($q) => $q->whereDate('session_date', $date))
            ->with(['user', 'session.course', 'takenBy'])
            ->get()
            ->sortBy([
                fn ($record) => $record->session?->session_title ?? '',
                fn ($record) => $record->user?->first_name ?? '',
                fn ($record) => $record->user?->second_name ?? '',
            ])
            ->values();
    }

    private function recordsForSessionGroup(Builder $baseQuery, int|string $sessionId): Collection
    {
        return (clone $baseQuery)
            ->where('session_id', $sessionId)
            ->with(['user', 'session.course', 'takenBy'])
            ->get()
            ->sortBy([
                fn ($record) => $record->user?->first_name ?? '',
                fn ($record) => $record->user?->second_name ?? '',
            ])
            ->values();
    }

    /** @param array{enrolled: int, recorded: int, missing: int, rows: list<array{user: User, attendance: ?Attendance, missing: bool}>}|null $roster */
    private function formatGroup(
        string $key,
        string $heading,
        Collection $records,
        ?string $meta,
        ?Session $session = null,
        ?array $roster = null,
    ): array {
        return [
            'key' => $key,
            'heading' => $heading,
            'meta' => $meta,
            'records' => $records,
            'stats' => $this->statsForRecords($records),
            'session' => $session,
            'roster' => $roster,
        ];
    }

    /** @return array{total: int, present: int, absent: int, late: int, permission: int} */
    private function statsForRecords(Collection $records): array
    {
        return [
            'total' => $records->count(),
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'permission' => $records->where('status', 'Permission')->count(),
        ];
    }

    private function paginateCollection(Collection $items, int $perPage, int $page, Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    // View attendance records for a specific user (for admin and instructor)
    public function viewUserAttendance($userId)
    {
        $user = User::findOrFail($userId);
        $attendanceRecords = $this->attendanceRecordsForUser($userId);

        // Get overall statistics
        $overallStats = $this->getOverallStats();

        // Get monthly statistics
        $monthlyStats = $this->getMonthlyStats($userId);

        return view('attendance.user', compact('user', 'attendanceRecords', 'overallStats', 'monthlyStats'));
    }

    public function viewMyAttendance()
    {
        $user = auth()->user();
        $attendanceRecords = $this->attendanceRecordsForUser($user->user_id);

        // Get overall statistics
        $overallStats = $this->getOverallStats();

        // Get monthly statistics
        $monthlyStats = $this->getMonthlyStats($user->user_id);

        return view('attendance.my', compact('attendanceRecords', 'overallStats', 'monthlyStats'));
    }

    public function userReport($userId)
    {
        return $this->viewUserAttendance($userId);
    }

    // Update attendance status
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Present,Absent,Late,Permission',
            'permission_reason' => 'required_if:status,Permission|nullable|string|max:255'
        ]);

        $attendance = Attendance::findOrFail($id);
        $attendance->status = $request->status;
        $attendance->permission_reason = $request->permission_reason;
        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الحالة بنجاح'
        ]);
    }

    // Update permission reason
    public function updatePermissionReason(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->permission_reason = $request->permission_reason;
        $attendance->save();

        return response()->json(['success' => true]);
    }

    // View attendance records for a specific date
    public function viewAttendanceByDate($date)
    {
        $attendanceRecords = Attendance::with(['session', 'user', 'takenBy'])
            ->whereHas('session', function ($sessionQuery) use ($date) {
                $sessionQuery->whereDate('session_date', $date);
            })
            ->orderByDesc('attendance_time')
            ->get();

        // Get overall statistics
        $overallStats = $this->getOverallStats();

        // Get session statistics
        $sessionStats = $this->getSessionStats($date);

        return view('attendance.date', compact('attendanceRecords', 'date', 'overallStats', 'sessionStats'));
    }

    // Helper methods for statistics
    private function getOverallStatistics()
    {
        return $this->scopeToStudents(Attendance::query())
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN attendance.status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN attendance.status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN attendance.status = "Late" THEN 1 ELSE 0 END) as late'),
            ])
            ->first();
    }

    private function getDailyStatistics()
    {
        return $this->scopeToStudents(
            Attendance::query()->join('session', 'attendance.session_id', '=', 'session.session_id')
        )
            ->selectRaw($this->sessionDateSql().' as date, COUNT(*) as total, SUM(CASE WHEN attendance.status = "Present" THEN 1 ELSE 0 END) as present')
            ->groupByRaw($this->sessionDateSql())
            ->orderByRaw($this->sessionDateSql().' DESC')
            ->limit(5)
            ->get();
    }

    private function getUserStatistics()
    {
        $query = $this->scopeToStudents(
            Attendance::query()
                ->join('user', 'attendance.user_id', '=', 'user.user_id')
        )
            ->select([
                'user.user_id',
                'user.first_name',
                'user.second_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN attendance.status = "Present" THEN 1 ELSE 0 END) as present'),
            ])
            ->groupBy('user.user_id', 'user.first_name', 'user.second_name')
            ->havingRaw('COUNT(*) > 0');

        if (DB::connection()->getDriverName() === 'sqlite') {
            return $query->orderByDesc('present')->orderByDesc('total')->limit(5)->get();
        }

        return $query
            ->orderByRaw('SUM(CASE WHEN attendance.status = "Present" THEN 1 ELSE 0 END) / COUNT(*) DESC')
            ->limit(5)
            ->get();
    }

    private function getSessionStatistics()
    {
        return $this->scopeToStudents(
            Attendance::query()->join('session', 'attendance.session_id', '=', 'session.session_id')
        )
            ->select([
                'session.session_title',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN attendance.status = "Present" THEN 1 ELSE 0 END) as present'),
            ])
            ->groupBy('session.session_title')
            ->orderByRaw('COUNT(*) DESC')
            ->get();
    }

    private function getMonthlyStatistics()
    {
        return $this->scopeToStudents(
            Attendance::query()->join('session', 'attendance.session_id', '=', 'session.session_id')
        )
            ->selectRaw($this->sessionMonthSql().' as month, COUNT(*) as total, SUM(CASE WHEN attendance.status = "Present" THEN 1 ELSE 0 END) as present')
            ->groupByRaw($this->sessionMonthSql())
            ->orderByRaw($this->sessionMonthSql().' DESC')
            ->get();
    }

    private function getUserStats()
    {
        return $this->scopeToStudents(
            Attendance::query()->join('user', 'attendance.user_id', '=', 'user.user_id')
        )
            ->selectRaw('
                user.user_id,
                user.first_name,
                user.second_name,
                COUNT(*) as total_records,
                SUM(CASE WHEN status IN ("Present", "Permission") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late_count
            ')
            ->groupBy('user.user_id', 'user.first_name', 'user.second_name')
            ->orderByRaw('SUM(CASE WHEN status IN ("Present", "Permission") THEN 1 ELSE 0 END) / COUNT(*) DESC')
            ->limit(5)
            ->get();
    }

    private function getOverallStats()
    {
        return DB::table('attendance')
            ->selectRaw('
                COUNT(*) as total_records,
                SUM(CASE WHEN status IN ("Present", "Permission") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late_count
            ')
            ->first();
    }

    private function getDailyStats()
    {
        return DB::table('attendance')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->selectRaw('
                DATE(session.session_date) as date,
                COUNT(*) as total_records,
                SUM(CASE WHEN status IN ("Present", "Permission") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late_count
            ')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();
    }

    private function getSessionStats($date)
    {
        return DB::table('attendance')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->whereDate('session.session_date', $date)
            ->selectRaw('
                session.session_title,
                COUNT(*) as total_records,
                SUM(CASE WHEN status IN ("Present", "Permission") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late_count
            ')
            ->groupBy('session.session_title')
            ->get();
    }

    private function getMonthlyStats($userId)
    {
        return DB::table('attendance')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('attendance.user_id', $userId)
            ->selectRaw('
                '.$this->sessionMonthSql().' as month,
                COUNT(*) as total_records,
                SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendance.status = "Absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN attendance.status = "Late" THEN 1 ELSE 0 END) as late_count
            ')
            ->groupByRaw($this->sessionMonthSql())
            ->orderByRaw($this->sessionMonthSql().' DESC')
            ->get();
    }

    // Comprehensive attendance report for all users
    public function attendanceReport(Request $request)
    {
        [$users, $overallStats] = $this->attendanceReportData();

        return match ($request->query('export')) {
            'excel' => $this->downloadAttendanceReportExcel($users, $overallStats),
            'pdf' => $this->downloadAttendanceReportPdf($users, $overallStats),
            default => view('attendance.report', compact('users', 'overallStats')),
        };
    }

    /** @return array{0: Collection, 1: array<string, mixed>} */
    private function attendanceReportData(): array
    {
        $totalSessionsInDB = DB::table('session')->count();
        $studentIds = $this->enrolledStudentIds();

        $users = DB::table('user')
            ->whereIn('user.user_id', $studentIds)
            ->leftJoin('attendance', 'attendance.user_id', '=', 'user.user_id')
            ->select([
                'user.user_id',
                'user.first_name',
                'user.second_name',
                'user.mobile_number',
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END), 0) as attended_sessions'),
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status = "Absent" THEN 1 ELSE 0 END), 0) as absent_sessions'),
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status = "Late" THEN 1 ELSE 0 END), 0) as late_sessions'),
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission", "Absent", "Late") THEN 1 ELSE 0 END), 0) as total_sessions'),
                DB::raw('CASE 
                    WHEN COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission", "Absent", "Late") THEN 1 ELSE 0 END), 0) > 0 
                    THEN ROUND((COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END), 0) / 
                               COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission", "Absent", "Late") THEN 1 ELSE 0 END), 0)) * 100, 2)
                    ELSE 0 
                END as attendance_percentage')
            ])
            ->groupBy('user.user_id', 'user.first_name', 'user.second_name', 'user.mobile_number')
            ->orderByDesc('attendance_percentage')
            ->get();

        $overallStats = [
            'total_users' => $users->count(),
            'total_sessions' => $totalSessionsInDB,
            'total_attended' => $users->sum('attended_sessions'),
            'total_absent' => $users->sum('absent_sessions'),
            'total_late' => $users->sum('late_sessions'),
            'average_attendance' => $users->avg('attendance_percentage'),
        ];

        return [$users, $overallStats];
    }

    /** @param array<string, mixed> $overallStats */
    private function downloadAttendanceReportExcel(Collection $users, array $overallStats): StreamedResponse
    {
        $filename = 'attendance-report-'.now()->format('Y-m-d').'.xls';

        return response()->streamDownload(function () use ($users, $overallStats) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [__('pages.attendance_report_title')]);
            fputcsv($handle, [now()->format('Y-m-d H:i')]);
            fputcsv($handle, []);
            fputcsv($handle, [
                __('pages.total_students'),
                __('pages.total_lectures'),
                __('pages.total_present'),
                __('pages.total_absent'),
                __('pages.avg_attendance_rate'),
            ]);
            fputcsv($handle, [
                $overallStats['total_users'],
                $overallStats['total_sessions'],
                $overallStats['total_attended'],
                $overallStats['total_absent'],
                number_format((float) $overallStats['average_attendance'], 1).'%',
            ]);
            fputcsv($handle, []);
            fputcsv($handle, [
                __('pages.student_name'),
                __('pages.phone_number'),
                __('pages.total_sessions_count'),
                __('pages.present_times'),
                __('pages.absent_times'),
                __('pages.late_times'),
                __('pages.attendance_rate'),
            ]);

            foreach ($users as $user) {
                fputcsv($handle, [
                    trim($user->first_name.' '.$user->second_name),
                    $user->mobile_number,
                    $user->total_sessions,
                    $user->attended_sessions,
                    $user->absent_sessions,
                    $user->late_sessions,
                    number_format((float) $user->attendance_percentage, 1).'%',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /** @param array<string, mixed> $overallStats */
    private function downloadAttendanceReportPdf(Collection $users, array $overallStats)
    {
        return Pdf::loadView('attendance.report-pdf', compact('users', 'overallStats'))
            ->setPaper('a4', 'landscape')
            ->download('attendance-report-'.now()->format('Y-m-d').'.pdf');
    }
}
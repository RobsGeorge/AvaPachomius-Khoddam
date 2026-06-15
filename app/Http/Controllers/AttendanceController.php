<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Course;
use App\Models\Role;

use Illuminate\Http\Request;
use App\Models\Session;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    private const SESSION_DATE_SQL = 'DATE(DATE_ADD(session.session_date, INTERVAL 3 HOUR))';

    private const SESSION_MONTH_SQL = 'DATE_FORMAT(DATE_ADD(session.session_date, INTERVAL 3 HOUR), "%Y-%m")';

    private function studentRoleId(): ?int
    {
        return Role::where('role_name', 'Student')->value('role_id');
    }

    /** Limit attendance queries to enrolled students when the Student role exists. */
    private function scopeToStudents(Builder $query, string $userIdColumn = 'attendance.user_id'): Builder
    {
        $studentRoleId = $this->studentRoleId();

        if (! $studentRoleId) {
            return $query;
        }

        if (! Schema::hasTable('user_course_role')) {
            return $query;
        }

        return $query->whereIn($userIdColumn, function ($sub) use ($studentRoleId) {
            $sub->select('user_id')
                ->from('user_course_role')
                ->where('role_id', $studentRoleId);
        });
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function enrolledStudentIds()
    {
        $studentRoleId = $this->studentRoleId();

        return DB::table('user_course_role')
            ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
            ->distinct()
            ->pluck('user_id');
    }

    private function attendanceRecordsForUser(int|string $userId)
    {
        return Attendance::with(['session', 'takenBy'])
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('attendance.user_id', $userId)
            ->orderBy('session.session_date', 'desc')
            ->select([
                'attendance.*',
                DB::raw('DATE(DATE_ADD(session.session_date, INTERVAL 3 HOUR)) as session_date'),
                DB::raw("CONCAT(DATE_FORMAT(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR), '%h:%i'), ' ', CASE WHEN HOUR(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR)) < 12 THEN 'ص' ELSE 'م' END) as attendance_time"),
            ])
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

        $sessions = Session::with('course')
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
        $query = $this->scopeToStudents(
            Attendance::with(['user', 'session', 'takenBy'])
        );

        if ($request->filled('session_date')) {
            $query->whereHas('session', function ($sessionQuery) use ($request) {
                $sessionQuery->whereDate('session_date', $request->input('session_date'));
            });
        }

        $attendanceRecords = $query
            ->orderByDesc('attendance_time')
            ->paginate(20);

        $overallStats = $this->getOverallStatistics();
        $dailyStats = $this->getDailyStatistics();
        $userStats = $this->getUserStatistics();

        return view('attendance.all', compact('attendanceRecords', 'overallStats', 'dailyStats', 'userStats'));
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
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->whereDate('session.session_date', $date)
            ->orderBy('session.session_date', 'desc')
            ->select([
                'attendance.*',
                DB::raw('DATE(DATE_ADD(session.session_date, INTERVAL 3 HOUR)) as session_date'),
                DB::raw("CONCAT(DATE_FORMAT(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR), '%h:%i'), ' ', CASE WHEN HOUR(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR)) < 12 THEN 'ص' ELSE 'م' END) as attendance_time")
            ])
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
            ->selectRaw(self::SESSION_DATE_SQL.' as date, COUNT(*) as total, SUM(CASE WHEN attendance.status = "Present" THEN 1 ELSE 0 END) as present')
            ->groupByRaw(self::SESSION_DATE_SQL)
            ->orderByRaw(self::SESSION_DATE_SQL.' DESC')
            ->limit(5)
            ->get();
    }

    private function getUserStatistics()
    {
        return $this->scopeToStudents(
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
            ->havingRaw('COUNT(*) > 0')
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
            ->selectRaw(self::SESSION_MONTH_SQL.' as month, COUNT(*) as total, SUM(CASE WHEN attendance.status = "Present" THEN 1 ELSE 0 END) as present')
            ->groupByRaw(self::SESSION_MONTH_SQL)
            ->orderByRaw(self::SESSION_MONTH_SQL.' DESC')
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
                DATE_FORMAT(session.session_date, "%Y-%m") as month,
                COUNT(*) as total_records,
                SUM(CASE WHEN status IN ("Present", "Permission") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late_count
            ')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get();
    }

    // Comprehensive attendance report for all users
    public function attendanceReport()
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

        // Calculate overall statistics
        $overallStats = [
            'total_users' => $users->count(),
            'total_sessions' => $totalSessionsInDB,
            'total_attended' => $users->sum('attended_sessions'),
            'total_absent' => $users->sum('absent_sessions'),
            'total_late' => $users->sum('late_sessions'),
            'average_attendance' => $users->avg('attendance_percentage')
        ];

        return view('attendance.report', compact('users', 'overallStats'));
    }
}

?>
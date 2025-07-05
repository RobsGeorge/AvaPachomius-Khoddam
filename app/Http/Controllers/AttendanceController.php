<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Course;

use Illuminate\Http\Request;
use App\Models\Session;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    // Show today's sessions page with the scanned user_id
    public function showTodaySessions(Request $request)
    {
        $userId = $request->query('user_id');
        $user = User::find($userId);



        // Optional: validate user exists
        if (!$userId || !User::find($userId)) {
            abort(404, 'المستخدم غير موجود');
        }

        // Optional: ensure current user is admin/instructor (middleware)
        // Or add extra validation if needed

        $today = date('Y-m-d');
        $sessions = Session::whereDate('session_date', $today)->get();

        return view('attendance.sessions', compact('sessions', 'userId', 'user'));
    }

    // Record attendance for the student (userId) for a given session
    public function recordAttendance(Request $request, Session $session)
    {
        if (!auth()->check()) {
            // Not logged in — redirect or throw error
            return redirect()->route('login')->withErrors('يرجى تسجيل الدخول أولاً');
        }

        $userTakingAttendanceId = auth()->id();
        $studentUserId = $request->input('student_user_id');

        if (!$studentUserId || !User::find($studentUserId)) {
            return redirect()->back()->withErrors('لم يتم تحديد المستخدم لتسجيل الحضور أو المستخدم غير موجود.');
        }

        // Prevent duplicate attendance record
        $exists = Attendance::where('session_id', $session->session_id)
                    ->where('user_id', $studentUserId)
                    ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'تم تسجيل الحضور لهذا المستخدم بالفعل.');
        }

        Attendance::create([
            'session_id' => $session->session_id,
            'user_id' => $studentUserId,
            'taken_by_id' => $userTakingAttendanceId,
        ]);

        return redirect()->back()->with('success', 'تم تسجيل الحضور بنجاح.');
    }

    // View all attendance records (for admin and instructor)
    public function viewAllAttendance(Request $request)
    {
        $query = Attendance::with(['user', 'session', 'takenBy'])
            ->join('user_course_role', 'attendance.user_id', '=', 'user_course_role.user_id')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('user_course_role.role_id', '=', 1)
            ->select([
                'attendance.*',
                'session.session_title',
                'user_course_role.role_id',
                DB::raw('DATE(DATE_ADD(session.session_date, INTERVAL 3 HOUR)) as session_date'),
                DB::raw("CONCAT(DATE_FORMAT(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR), '%h:%i'), ' ', CASE WHEN HOUR(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR)) < 12 THEN 'ص' ELSE 'م' END) as attendance_time")
            ]);

        // Filter by session date
        if ($request->filled('session_date')) {
            $query->whereDate('session.session_date', $request->input('session_date'));
        }

        $query->orderBy('attendance.attendance_time', 'desc');

        $attendanceRecords = $query->paginate(20);

        // Get overall statistics
        $overallStats = $this->getOverallStatistics();
        $dailyStats = $this->getDailyStatistics();
        $userStats = $this->getUserStatistics();

        return view('attendance.all', compact('attendanceRecords', 'overallStats', 'dailyStats', 'userStats'));
    }

    // View attendance records for a specific user (for admin and instructor)
    public function viewUserAttendance($userId)
    {
        $user = User::findOrFail($userId);
        $attendanceRecords = Attendance::with(['session', 'takenBy'])
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('attendance.user_id', $userId)
            ->orderBy('session.session_date', 'desc')
            ->select([
                'attendance.*',
                DB::raw('DATE(DATE_ADD(session.session_date, INTERVAL 3 HOUR)) as session_date'),
                DB::raw("CONCAT(DATE_FORMAT(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR), '%h:%i'), ' ', CASE WHEN HOUR(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR)) < 12 THEN 'ص' ELSE 'م' END) as attendance_time")
            ])
            ->get();

        // Get overall statistics
        $overallStats = $this->getOverallStats();

        // Get monthly statistics
        $monthlyStats = $this->getMonthlyStats($userId);

        return view('attendance.user', compact('user', 'attendanceRecords', 'overallStats', 'monthlyStats'));
    }

    public function viewMyAttendance()
    {
        $user = auth()->user();
        $attendanceRecords = Attendance::with(['session', 'takenBy'])
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('user_id', $user->user_id)
            ->orderBy('session.session_date', 'desc')
            ->select([
                'attendance.*',
                DB::raw('DATE(DATE_ADD(session.session_date, INTERVAL 3 HOUR)) as session_date'),
                DB::raw("CONCAT(DATE_FORMAT(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR), '%h:%i'), ' ', CASE WHEN HOUR(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR)) < 12 THEN 'ص' ELSE 'م' END) as attendance_time")
            ])
            ->get();

        // Get overall statistics
        $overallStats = $this->getOverallStats();

        // Get monthly statistics
        $monthlyStats = $this->getMonthlyStats($user->user_id);

        return view('attendance.my', compact('attendanceRecords', 'overallStats', 'monthlyStats'));
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
        return Attendance::join('user_course_role', 'attendance.user_id', '=', 'user_course_role.user_id')
            ->where('user_course_role.role_id', '=', 1)
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late')
            ])
            ->first();
    }

    private function getDailyStatistics()
    {
        return Attendance::join('user_course_role', 'attendance.user_id', '=', 'user_course_role.user_id')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('user_course_role.role_id', '=', 1)
            ->select([
                DB::raw('DATE(DATE_ADD(session.session_date, INTERVAL 3 HOUR)) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present')
            ])
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();
    }

    private function getUserStatistics()
    {
        return Attendance::join('user_course_role', 'attendance.user_id', '=', 'user_course_role.user_id')
            ->join('user', 'attendance.user_id', '=', 'user.user_id')
            ->where('user_course_role.role_id', '=', 1)
            ->select([
                'user.user_id',
                'user.first_name',
                'user.second_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present')
            ])
            ->groupBy('user.user_id', 'user.first_name', 'user.second_name')
            ->having('total', '>', 0)
            ->orderByRaw('(SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) / COUNT(*)) DESC')
            ->limit(5)
            ->get();
    }

    private function getSessionStatistics()
    {
        return Attendance::join('user_course_role', 'attendance.user_id', '=', 'user_course_role.user_id')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('user_course_role.role_id', '=', 1)
            ->select([
                'session.session_title',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present')
            ])
            ->groupBy('session.session_title')
            ->orderBy('total', 'desc')
            ->get();
    }

    private function getMonthlyStatistics()
    {
        return Attendance::join('user_course_role', 'attendance.user_id', '=', 'user_course_role.user_id')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('user_course_role.role_id', '=', 1)
            ->select([
                DB::raw('DATE_FORMAT(DATE_ADD(session.session_date, INTERVAL 3 HOUR), "%Y-%m") as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present')
            ])
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get();
    }

    private function getUserStats()
    {
        return DB::table('attendance')
            ->join('users', 'attendance.user_id', '=', 'users.user_id')
            ->selectRaw('
                users.user_id,
                users.name,
                COUNT(*) as total_records,
                SUM(CASE WHEN status IN ("Present", "Permission") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late_count
            ')
            ->groupBy('users.user_id', 'users.name')
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
        // Get total sessions count from database
        $totalSessionsInDB = DB::table('session')->count();

        $users = DB::table('user')
            ->join('user_course_role', 'user.user_id', '=', 'user_course_role.user_id')
            ->where('user_course_role.role_id', 1) // Assuming role_id 1 is for students
            ->select([
                'user.user_id',
                'user.first_name',
                'user.second_name',
                'user.mobile_number',
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END), 0) as attended_sessions'),
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status = "Absent" THEN 1 ELSE 0 END), 0) as absent_sessions'),
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status = "Late" THEN 1 ELSE 0 END), 0) as late_sessions'),
                DB::raw('COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END), 0) + 
                         COALESCE(SUM(CASE WHEN attendance.status = "Absent" THEN 1 ELSE 0 END), 0) + 
                         COALESCE(SUM(CASE WHEN attendance.status = "Late" THEN 1 ELSE 0 END), 0) as total_sessions'),
                DB::raw('CASE 
                    WHEN (COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END), 0) + 
                          COALESCE(SUM(CASE WHEN attendance.status = "Absent" THEN 1 ELSE 0 END), 0) + 
                          COALESCE(SUM(CASE WHEN attendance.status = "Late" THEN 1 ELSE 0 END), 0)) > 0 
                    THEN ROUND((COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END), 0) / 
                               (COALESCE(SUM(CASE WHEN attendance.status IN ("Present", "Permission") THEN 1 ELSE 0 END), 0) + 
                                COALESCE(SUM(CASE WHEN attendance.status = "Absent" THEN 1 ELSE 0 END), 0) + 
                                COALESCE(SUM(CASE WHEN attendance.status = "Late" THEN 1 ELSE 0 END), 0))) * 100, 2)
                    ELSE 0 
                END as attendance_percentage')
            ])
            ->leftJoin('attendance', 'attendance.user_id', '=', 'user.user_id')
            ->groupBy('user.user_id', 'user.first_name', 'user.second_name', 'user.mobile_number')
            ->orderBy('attendance_percentage', 'desc')
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
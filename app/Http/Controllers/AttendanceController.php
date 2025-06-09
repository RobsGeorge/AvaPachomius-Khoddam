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

        // Optional: ensure current user is Admin/Instructor (middleware)
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
                DB::raw('DATE_ADD(session.session_date, INTERVAL 3 HOUR) as session_date'),
                DB::raw("CONCAT(DATE_FORMAT(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR), '%h:%i'), ' ', CASE WHEN HOUR(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR)) < 12 THEN 'ص' ELSE 'م' END) as attendance_time")
            ]);

        // Filter by session date
        if ($request->filled('session_date')) {
            $query->whereDate('session.session_date', $request->input('session_date'));
        }

        $query->orderBy('session.session_date', 'desc')
              ->orderBy('attendance.created_at', 'desc');

        $attendanceRecords = $query->paginate(20);

        return view('attendance.all', compact('attendanceRecords'));
    }

    // View attendance records for a specific user (for admin and instructor)
    public function viewUserAttendance($userId)
    {
        $user = User::findOrFail($userId);
        $attendanceRecords = Attendance::with(['session', 'user'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('attendance.user', [
            'user' => $user,
            'attendanceRecords' => $attendanceRecords
        ]);
    }

    public function viewMyAttendance()
    {
        $user = auth()->user();
        $attendanceRecords = Attendance::with(['session'])
            ->where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->select([
                'attendance.*',
                DB::raw('DATE_ADD(session.session_date, INTERVAL 3 HOUR) as session_date'),
                DB::raw("CONCAT(DATE_FORMAT(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR), '%h:%i'), ' ', CASE WHEN HOUR(DATE_ADD(attendance.attendance_time, INTERVAL 3 HOUR)) < 12 THEN 'ص' ELSE 'م' END) as attendance_time")
            ]);

        return view('attendance.my', [
            'user' => $user,
            'attendanceRecords' => $attendanceRecords
        ]);
    }

    // Update attendance status
    public function updateStatus(Request $request, $attendanceId)
    {
        try {
            $attendance = Attendance::findOrFail($attendanceId);
            $previousStatus = $attendance->status;
            
            $attendance->status = $request->input('status');
            $attendance->save();

            return response()->json([
                'success' => true,
                'previousStatus' => $previousStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الحالة'
            ], 500);
        }
    }

    // Update permission reason
    public function updatePermissionReason(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->permission_reason = $request->permission_reason;
        $attendance->save();

        return response()->json(['success' => true]);
    }
}

?>
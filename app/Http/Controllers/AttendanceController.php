<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Course;

use Illuminate\Http\Request;
use App\Models\Session;
use App\Models\Attendance;

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
        $query = Attendance::with(['user', 'session', 'takenBy']);

        // Search by name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('second_name', 'like', "%{$search}%")
                  ->orWhere('third_name', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereHas('session', function($q) use ($request) {
                $q->whereDate('session_date', '>=', $request->input('date_from'));
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('session', function($q) use ($request) {
                $q->whereDate('session_date', '<=', $request->input('date_to'));
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sorting
        if ($request->filled('sort_by')) {
            switch ($request->input('sort_by')) {
                case 'date_desc':
                    $query->join('session', 'attendance.session_id', '=', 'session.session_id')
                          ->orderBy('session.session_date', 'desc')
                          ->orderBy('attendance.attendance_time', 'desc');
                    break;
                case 'date_asc':
                    $query->join('session', 'attendance.session_id', '=', 'session.session_id')
                          ->orderBy('session.session_date', 'asc')
                          ->orderBy('attendance.attendance_time', 'asc');
                    break;
                case 'name_asc':
                    $query->join('user', 'attendance.user_id', '=', 'user.user_id')
                          ->orderBy('user.first_name', 'asc')
                          ->orderBy('user.second_name', 'asc')
                          ->orderBy('user.third_name', 'asc');
                    break;
                case 'name_desc':
                    $query->join('user', 'attendance.user_id', '=', 'user.user_id')
                          ->orderBy('user.first_name', 'desc')
                          ->orderBy('user.second_name', 'desc')
                          ->orderBy('user.third_name', 'desc');
                    break;
            }
        } else {
            // Default sorting by date descending
            $query->join('session', 'attendance.session_id', '=', 'session.session_id')
                  ->orderBy('session.session_date', 'desc')
                  ->orderBy('attendance.attendance_time', 'desc');
        }

        $attendanceRecords = $query->paginate(20);

        return view('attendance.all', compact('attendanceRecords'));
    }

    // View attendance records for a specific user (for admin and instructor)
    public function viewUserAttendance($userId)
    {
        $user = User::findOrFail($userId);
        $attendanceRecords = Attendance::with(['session', 'takenBy'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('attendance.user', compact('attendanceRecords', 'user'));
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
    public function updatePermissionReason(Request $request, $attendanceId)
    {
        try {
            $attendance = Attendance::findOrFail($attendanceId);
            $attendance->permission_reason = $request->input('permission_reason');
            $attendance->save();

            return response()->json([
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث سبب الإذن'
            ], 500);
        }
    }
}

?>
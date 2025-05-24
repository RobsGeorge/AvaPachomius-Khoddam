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

        // Optional: validate user exists
        if (!$userId || !User::find($userId)) {
            abort(404, 'المستخدم غير موجود');
        }

        // Optional: ensure current user is Admin/Instructor (middleware)
        // Or add extra validation if needed

        $today = date('Y-m-d');
        $sessions = Session::whereDate('session_date', $today)->get();

        return view('attendance.sessions', compact('sessions', 'userId'));
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
}

?>
<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Services\AttendanceCloseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionAttendanceController extends Controller
{
    public function __construct(
        private AttendanceCloseService $attendanceClose,
    ) {}

    public function fillMissing(Request $request, Session $session): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:Present,Absent,Late,Permission',
        ]);

        $status = $validated['status'] ?? 'Absent';
        $count = $this->attendanceClose->fillMissingRecords(
            $session,
            (int) auth()->user()->user_id,
            $status,
        );

        return redirect()
            ->route('attendance.all', [
                'filter_by' => 'session',
                'session_id' => $session->session_id,
            ])
            ->with('success', __('pages.attendance_fill_missing_success', ['count' => $count]));
    }

    public function store(Request $request, Session $session): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:user,user_id',
            'status' => 'required|in:Present,Absent,Late,Permission',
            'permission_reason' => 'required_if:status,Permission|nullable|string|max:255',
            'allow_non_enrolled' => 'sometimes|boolean',
        ]);

        $attendance = $this->attendanceClose->createOrUpdateRecord(
            $session,
            (int) $validated['user_id'],
            $validated['status'],
            (int) auth()->user()->user_id,
            $validated['permission_reason'] ?? null,
            (bool) ($validated['allow_non_enrolled'] ?? false),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('pages.attendance_record_saved'),
                'attendance_id' => $attendance->attendance_id,
            ]);
        }

        return redirect()
            ->route('attendance.all', [
                'filter_by' => 'session',
                'session_id' => $session->session_id,
            ])
            ->with('success', __('pages.attendance_record_saved'));
    }

    public function searchStudents(Request $request, Session $session): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:100',
            'include_non_enrolled' => 'sometimes|boolean',
        ]);

        $users = $this->attendanceClose->searchStudentsForSession(
            $session,
            $validated['q'],
            (bool) ($validated['include_non_enrolled'] ?? false),
        );

        return response()->json([
            'results' => $users->map(fn ($user) => [
                'user_id' => $user->user_id,
                'label' => trim($user->first_name.' '.$user->second_name.' '.($user->third_name ?? '')),
                'mobile_number' => $user->mobile_number,
            ])->values(),
        ]);
    }
}

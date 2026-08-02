<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Session;
use App\Services\AttendanceCloseService;
use App\Services\AuditLogService;
use App\Services\Maturity\GuardianVisibilityGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Guardian-mediated check-in: guardian User session writes the CHILD's person_id.
 */
class GuardianAttendanceController extends Controller
{
    public function __construct(
        private AttendanceCloseService $attendanceClose,
        private GuardianVisibilityGate $visibility,
    ) {}

    public function store(Request $request, Session $session): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'person_id' => 'required|integer|exists:people,person_id',
            'status' => 'nullable|in:Present,Absent,Late,Permission',
            'permission_reason' => 'required_if:status,Permission|nullable|string|max:255',
        ]);

        $guardian = $request->user();
        $ward = Person::withoutTenancy()->active()->findOrFail((int) $validated['person_id']);

        if (! $this->visibility->guardianMaySee($guardian, $ward, GuardianVisibilityGate::CATEGORY_CUSTODIAL)) {
            abort(403, __('pages.attendance_guardian_forbidden'));
        }

        if ($session->session_date && $session->session_date->toDateString() !== now()->toDateString()) {
            return $this->fail($request, __('pages.attendance_not_today_session'));
        }

        if ($session->isAttendanceClosed()) {
            return $this->fail($request, __('pages.attendance_session_closed'));
        }

        $status = $validated['status'] ?? 'Present';

        $attendance = $this->attendanceClose->createOrUpdateForPerson(
            $session,
            (int) $ward->person_id,
            $status,
            (int) $guardian->user_id,
            $validated['permission_reason'] ?? null,
            allowNonEnrolled: true,
        );

        AuditLogService::recordEvent('attendance.guardian_check_in', [
            'attendance_id' => $attendance->attendance_id,
            'ward_person_id' => $ward->person_id,
            'guardian_user_id' => $guardian->user_id,
            'session_id' => $session->session_id,
            'status' => $status,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('pages.attendance_record_saved'),
                'attendance_id' => $attendance->attendance_id,
                'person_id' => $attendance->person_id,
                'user_id' => $attendance->user_id,
            ]);
        }

        return redirect()->back()->with('success', __('pages.attendance_record_saved'));
    }

    private function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return redirect()->back()->with('error', $message);
    }
}

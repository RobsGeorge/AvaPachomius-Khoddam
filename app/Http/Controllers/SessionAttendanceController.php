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
            'person_id' => 'nullable|integer|exists:people,person_id',
            'user_id' => 'nullable|integer|exists:user,user_id',
            'status' => 'required|in:Present,Absent,Late,Permission',
            'permission_reason' => 'required_if:status,Permission|nullable|string|max:255',
            'allow_non_enrolled' => 'sometimes|boolean',
        ]);

        if (empty($validated['person_id']) && empty($validated['user_id'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('pages.student_not_found'),
                ], 422);
            }

            return redirect()->back()->withErrors([
                'person_id' => __('pages.student_not_found'),
            ]);
        }

        $allowNonEnrolled = (bool) ($validated['allow_non_enrolled'] ?? false);
        $actorId = (int) auth()->user()->user_id;

        if (! empty($validated['person_id'])) {
            $attendance = $this->attendanceClose->createOrUpdateForPerson(
                $session,
                (int) $validated['person_id'],
                $validated['status'],
                $actorId,
                $validated['permission_reason'] ?? null,
                $allowNonEnrolled,
            );
        } else {
            $attendance = $this->attendanceClose->createOrUpdateRecord(
                $session,
                (int) $validated['user_id'],
                $validated['status'],
                $actorId,
                $validated['permission_reason'] ?? null,
                $allowNonEnrolled,
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('pages.attendance_record_saved'),
                'attendance_id' => $attendance->attendance_id,
                'person_id' => $attendance->person_id,
                'user_id' => $attendance->user_id,
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

        $includeNonEnrolled = (bool) ($validated['include_non_enrolled'] ?? false);

        $people = $this->attendanceClose->searchPeopleForSession(
            $session,
            $validated['q'],
            $includeNonEnrolled,
        );

        if ($people->isNotEmpty()) {
            return response()->json([
                'results' => $people->values(),
            ]);
        }

        // Legacy user search fallback when people table empty / no matches.
        $users = $this->attendanceClose->searchStudentsForSession(
            $session,
            $validated['q'],
            $includeNonEnrolled,
        );

        return response()->json([
            'results' => $users->map(fn ($user) => [
                'person_id' => $user->person_id ? (int) $user->person_id : null,
                'user_id' => $user->user_id,
                'label' => trim($user->first_name.' '.$user->second_name.' '.($user->third_name ?? '')),
                'mobile_number' => $user->mobile_number,
            ])->values(),
        ]);
    }
}

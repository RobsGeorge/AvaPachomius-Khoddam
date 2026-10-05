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
            'lecture_id' => 'nullable|integer|exists:lectures,lecture_id',
        ]);

        $status = $validated['status'] ?? 'Absent';
        $lectureId = isset($validated['lecture_id']) ? (int) $validated['lecture_id'] : null;
        $count = $this->attendanceClose->fillMissingRecords(
            $session,
            (int) auth()->user()->user_id,
            $status,
            $lectureId,
        );

        return redirect()
            ->route('attendance.all', array_filter([
                'filter_by' => 'session',
                'session_id' => $session->session_id,
                'lecture_id' => $lectureId,
            ]))
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
            'lecture_id' => 'nullable|integer|exists:lectures,lecture_id',
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
        $lectureId = isset($validated['lecture_id']) ? (int) $validated['lecture_id'] : null;

        if (! empty($validated['person_id'])) {
            $attendance = $this->attendanceClose->createOrUpdateForPerson(
                $session,
                (int) $validated['person_id'],
                $validated['status'],
                $actorId,
                $validated['permission_reason'] ?? null,
                $allowNonEnrolled,
                $lectureId,
            );
        } else {
            $attendance = $this->attendanceClose->createOrUpdateRecord(
                $session,
                (int) $validated['user_id'],
                $validated['status'],
                $actorId,
                $validated['permission_reason'] ?? null,
                $allowNonEnrolled,
                $lectureId,
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
            ->route('attendance.all', array_filter([
                'filter_by' => 'session',
                'session_id' => $session->session_id,
                'lecture_id' => $attendance->lecture_id,
            ]))
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

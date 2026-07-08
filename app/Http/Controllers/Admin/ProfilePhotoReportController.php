<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfilePhotoAdminService;
use App\Services\ProfilePhotoGateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfilePhotoReportController extends Controller
{
    public function __construct(
        private ProfilePhotoAdminService $adminService,
        private ProfilePhotoGateService $gate
    ) {}

    public function index(Request $request)
    {
        $filter = $request->query('filter');
        $students = $this->adminService->studentReport($filter);
        $settings = $this->gate->settings();

        $counts = [
            'not_started' => $this->adminService->studentReport('not_started')->count(),
            'in_grace' => $this->adminService->studentReport('in_grace')->count(),
            'overdue' => $this->adminService->studentReport('overdue')->count(),
            'pending_review' => $this->adminService->studentReport('pending_review')->count(),
            'approved' => $this->adminService->studentReport('approved')->count(),
            'rejected' => $this->adminService->studentReport('rejected')->count(),
        ];

        return view('admin.profile-photos.index', compact('students', 'settings', 'filter', 'counts'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'profile_photo_grace_days' => 'required|integer|min:1|max:90',
            'profile_photo_gate_enabled' => 'sometimes|boolean',
        ]);

        $this->adminService->updateSettings(
            (int) $data['profile_photo_grace_days'],
            $request->boolean('profile_photo_gate_enabled')
        );

        return back()->with('success', __('profile_photos.settings_saved'));
    }

    public function extendDeadline(Request $request, User $user)
    {
        $data = $request->validate([
            'profile_photo_deadline_at' => 'required|date|after:now',
        ]);

        $this->adminService->extendDeadline(
            $user,
            \Illuminate\Support\Carbon::parse($data['profile_photo_deadline_at'], $this->gate->timezone()),
            Auth::user()
        );

        return back()->with('success', __('profile_photos.deadline_extended', ['name' => $user->displayName()]));
    }

    public function resetGrace(User $user)
    {
        $this->adminService->resetGraceStart($user, Auth::user());

        return back()->with('success', __('profile_photos.grace_reset', ['name' => $user->displayName()]));
    }

    public function approve(User $user)
    {
        $this->adminService->approve($user, Auth::user());

        return back()->with('success', __('profile_photos.approved', ['name' => $user->displayName()]));
    }

    public function reject(Request $request, User $user)
    {
        $data = $request->validate([
            'profile_photo_rejection_note' => 'nullable|string|max:1000',
        ]);

        $this->adminService->reject($user, Auth::user(), $data['profile_photo_rejection_note'] ?? null);

        return back()->with('success', __('profile_photos.rejected', ['name' => $user->displayName()]));
    }
}

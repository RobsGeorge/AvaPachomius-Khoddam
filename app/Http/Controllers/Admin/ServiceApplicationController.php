<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchService;
use App\Models\ServiceApplication;
use App\Models\User;
use App\Services\ServiceApplicationService;
use App\Services\ServiceApplicationVisibility;
use Illuminate\Http\Request;

class ServiceApplicationController extends Controller
{
    public function __construct(
        private ServiceApplicationService $applications,
        private ServiceApplicationVisibility $visibility,
    ) {}

    public function index()
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canAccessAdminServiceApplications(), 403);

        $query = ServiceApplication::query()
            ->with(['user', 'service'])
            ->orderByDesc('submitted_at');

        $allowedServiceIds = $this->visibility->reviewableServiceIds($user);
        if ($allowedServiceIds !== null) {
            $query->whereIn('service_id', $allowedServiceIds->isEmpty() ? [-1] : $allowedServiceIds);
        }

        $applications = $query->paginate(30);

        return view('admin.service-applications.index', compact('applications'));
    }

    public function show(ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless(
            $user instanceof User && $this->visibility->canReviewApplication($user, (int) $serviceApplication->service_id),
            403
        );

        $serviceApplication->load(['user', 'service', 'form']);

        return view('admin.service-applications.show', ['application' => $serviceApplication]);
    }

    public function approve(Request $request, ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless(
            $user instanceof User && $this->visibility->canReviewApplication($user, (int) $serviceApplication->service_id),
            403
        );

        $validated = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $this->applications->approve($serviceApplication, $user, $validated['admin_note'] ?? null);

        return redirect()
            ->route('admin.service-applications.index')
            ->with('success', __('service.application_approved'));
    }

    public function reject(Request $request, ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless(
            $user instanceof User && $this->visibility->canReviewApplication($user, (int) $serviceApplication->service_id),
            403
        );

        $validated = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $this->applications->reject($serviceApplication, $user, $validated['admin_note'] ?? null);

        return redirect()
            ->route('admin.service-applications.index')
            ->with('success', __('service.application_rejected'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceApplication;
use App\Services\ServiceApplicationService;
use Illuminate\Http\Request;

class ServiceApplicationController extends Controller
{
    public function __construct(
        private ServiceApplicationService $applications,
    ) {}

    public function index()
    {
        $user = auth()->user();
        abort_unless($user && (($user->is_superadmin ?? false) || $user->canInSystem('service_application.review')), 403);

        $applications = ServiceApplication::query()
            ->with(['user', 'service'])
            ->orderByDesc('submitted_at')
            ->paginate(30);

        return view('admin.service-applications.index', compact('applications'));
    }

    public function show(ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless($user && (($user->is_superadmin ?? false) || $user->canInSystem('service_application.review')), 403);

        $serviceApplication->load(['user', 'service', 'form']);

        return view('admin.service-applications.show', ['application' => $serviceApplication]);
    }

    public function approve(Request $request, ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless($user && (($user->is_superadmin ?? false) || $user->canInSystem('service_application.review')), 403);

        $validated = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $this->applications->approve($serviceApplication, $user, $validated['admin_note'] ?? null);

        return redirect()
            ->route('admin.service-applications.index')
            ->with('success', __('service.application_approved'));
    }

    public function reject(Request $request, ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless($user && (($user->is_superadmin ?? false) || $user->canInSystem('service_application.review')), 403);

        $validated = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $this->applications->reject($serviceApplication, $user, $validated['admin_note'] ?? null);

        return redirect()
            ->route('admin.service-applications.index')
            ->with('success', __('service.application_rejected'));
    }
}

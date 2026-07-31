<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchService;
use App\Models\ServiceApplication;
use App\Models\User;
use App\Models\UserServiceRole;
use App\Services\RolePreviewService;
use App\Services\ServiceApplicationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ServiceApplicationController extends Controller
{
    public function __construct(
        private ServiceApplicationService $applications,
    ) {}

    public function index()
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canAccessAdminServiceApplications(), 403);

        $query = ServiceApplication::query()
            ->with(['user', 'service'])
            ->orderByDesc('submitted_at');

        $allowedServiceIds = $this->reviewableServiceIds($user);
        if ($allowedServiceIds !== null) {
            $query->whereIn('service_id', $allowedServiceIds->isEmpty() ? [-1] : $allowedServiceIds);
        }

        $applications = $query->paginate(30);

        return view('admin.service-applications.index', compact('applications'));
    }

    public function show(ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canReviewApplication($user, $serviceApplication), 403);

        $serviceApplication->load(['user', 'service', 'form']);

        return view('admin.service-applications.show', ['application' => $serviceApplication]);
    }

    public function approve(Request $request, ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canReviewApplication($user, $serviceApplication), 403);

        $validated = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $this->applications->approve($serviceApplication, $user, $validated['admin_note'] ?? null);

        return redirect()
            ->route('admin.service-applications.index')
            ->with('success', __('service.application_approved'));
    }

    public function reject(Request $request, ServiceApplication $serviceApplication)
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canReviewApplication($user, $serviceApplication), 403);

        $validated = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $this->applications->reject($serviceApplication, $user, $validated['admin_note'] ?? null);

        return redirect()
            ->route('admin.service-applications.index')
            ->with('success', __('service.application_rejected'));
    }

    private function canReviewApplication(User $user, ServiceApplication $application): bool
    {
        $service = $application->service;
        if (! $service instanceof ChurchService) {
            $service = ChurchService::query()
                ->withoutGlobalScope('church')
                ->find($application->service_id);
        }

        if (! $service) {
            return false;
        }

        return $user->canAccessAdminServiceApplications($service)
            && $this->serviceIdVisibleTo($user, (int) $service->service_id);
    }

    private function serviceIdVisibleTo(User $user, int $serviceId): bool
    {
        $allowed = $this->reviewableServiceIds($user);
        if ($allowed === null) {
            return true;
        }

        return $allowed->contains($serviceId);
    }

    /**
     * null = unrestricted (system / superadmin when tenancy dormant);
     * otherwise service IDs the reviewer may see.
     *
     * @return Collection<int, int>|null
     */
    private function reviewableServiceIds(User $admin): ?Collection
    {
        $systemWide = RolePreviewService::superadminBypassesPermissions($admin)
            || $admin->canInSystem('service_application.review');

        if ($systemWide) {
            if (TenantContext::enforced() && Schema::hasColumn('service', 'church_id')) {
                $churchId = TenantContext::id();

                return ChurchService::query()
                    ->withoutGlobalScope('church')
                    ->where('church_id', $churchId)
                    ->pluck('service_id')
                    ->values();
            }

            return null;
        }

        if (! Schema::hasTable('user_service_role')) {
            return collect();
        }

        $serviceIds = UserServiceRole::query()
            ->where('user_id', $admin->user_id)
            ->pluck('service_id')
            ->unique()
            ->filter()
            ->values();

        if ($serviceIds->isEmpty()) {
            return collect();
        }

        return ChurchService::query()
            ->withoutGlobalScope('church')
            ->whereIn('service_id', $serviceIds)
            ->get()
            ->filter(fn (ChurchService $service) => $admin->canAccessAdminServiceApplications($service))
            ->pluck('service_id')
            ->values();
    }
}

<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\User;
use App\Models\UserServiceRole;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Shared service-application list/show visibility for the hub and dedicated queue.
 * Keeps tenant + permission scope in one place (auth-scope harden).
 */
class ServiceApplicationVisibility
{
    /**
     * null = unrestricted (system / superadmin when tenancy dormant);
     * otherwise service IDs the reviewer may see.
     *
     * @return Collection<int, int>|null
     */
    public function reviewableServiceIds(User $admin): ?Collection
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

    public function canReviewApplication(User $user, int $serviceId): bool
    {
        $service = ChurchService::query()
            ->withoutGlobalScope('church')
            ->find($serviceId);

        if (! $service) {
            return false;
        }

        if (! $user->canAccessAdminServiceApplications($service)) {
            return false;
        }

        $allowed = $this->reviewableServiceIds($user);
        if ($allowed === null) {
            return true;
        }

        return $allowed->contains($serviceId);
    }
}

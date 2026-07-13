<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\User;
use App\Models\UserServiceRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ServiceContextService
{
    public const SESSION_KEY = 'current_service_id';

    public function currentService(): ?ChurchService
    {
        if (! ChurchService::tableReady()) {
            return null;
        }

        $id = session(self::SESSION_KEY);
        if (! $id) {
            return null;
        }

        return ChurchService::find($id);
    }

    public function setCurrentService(?ChurchService $service): void
    {
        if ($service) {
            session([self::SESSION_KEY => $service->service_id]);
        } else {
            session()->forget(self::SESSION_KEY);
        }
    }

    /** @return Collection<int, ChurchService> */
    public function selectableServices(?User $user): Collection
    {
        if (! ChurchService::tableReady()) {
            return collect();
        }

        if (! $user) {
            return collect();
        }

        if ($user->is_superadmin ?? false) {
            return ChurchService::query()
                ->where('status', ChurchService::STATUS_ACTIVE)
                ->orderBy('title')
                ->get();
        }

        if (! Schema::hasTable('user_service_role')) {
            return collect();
        }

        $ids = UserServiceRole::query()
            ->where('user_id', $user->user_id)
            ->pluck('service_id');

        return ChurchService::query()
            ->whereIn('service_id', $ids)
            ->where('status', ChurchService::STATUS_ACTIVE)
            ->orderBy('title')
            ->get();
    }

    public function resolveAccessibleService(User $user, mixed $serviceId = null): ?ChurchService
    {
        $selectable = $this->selectableServices($user);

        if ($serviceId) {
            $match = $selectable->firstWhere('service_id', (int) $serviceId);
            if ($match) {
                return $match;
            }
        }

        $current = $this->currentService();
        if ($current && $selectable->contains('service_id', $current->service_id)) {
            return $current;
        }

        return $selectable->first();
    }
}

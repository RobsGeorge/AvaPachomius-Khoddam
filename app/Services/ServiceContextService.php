<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\User;
use App\Models\UserServiceRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session as SessionStore;
use Illuminate\Validation\ValidationException;

class ServiceContextService
{
    public const SESSION_KEY = 'current_service_id';

    public function requiresServiceContext(?User $user): bool
    {
        if (! $user instanceof User || ! ChurchService::tableReady()) {
            return false;
        }

        return ! ($user->is_superadmin ?? false);
    }

    public function supportsOptionalServiceContext(?User $user): bool
    {
        return $user instanceof User
            && ChurchService::tableReady()
            && ($user->is_superadmin ?? false);
    }

    public function currentService(?User $user = null): ?ChurchService
    {
        if (! ChurchService::tableReady()) {
            return null;
        }

        $user ??= auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        $id = SessionStore::get(self::SESSION_KEY);
        if (! $id) {
            return null;
        }

        $service = ChurchService::find($id);
        if (! $service || ! $this->userCanSelectService($user, $service)) {
            return null;
        }

        return $service;
    }

    public function setCurrentService(User $user, ChurchService|int $service): bool
    {
        $model = $service instanceof ChurchService
            ? $service
            : ChurchService::find($service);

        if (! $model || ! $this->userCanSelectService($user, $model)) {
            throw ValidationException::withMessages([
                'service_id' => __('service.invalid_selection'),
            ]);
        }

        SessionStore::put(self::SESSION_KEY, $model->service_id);

        return $this->clearIncompatibleCourse($user, $model);
    }

    /**
     * When the active course belongs to a different service, drop course context
     * so academic tools do not stay scoped to the wrong department.
     */
    public function clearIncompatibleCourse(User $user, ?ChurchService $service = null): bool
    {
        $service ??= $this->currentService($user);
        $courseContext = app(CourseContextService::class);
        $course = $courseContext->currentCourse($user);

        if (! $service || ! $course) {
            return false;
        }

        if ((int) ($course->service_id ?? 0) === (int) $service->service_id) {
            return false;
        }

        $courseContext->clearCurrentCourse();

        return true;
    }

    public function clearCurrentService(): void
    {
        SessionStore::forget(self::SESSION_KEY);
    }

    public function userCanSelectService(User $user, ChurchService $service): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        if (! Schema::hasTable('user_service_role')) {
            return false;
        }

        return UserServiceRole::query()
            ->where('user_id', $user->user_id)
            ->where('service_id', $service->service_id)
            ->exists();
    }

    /** @return Collection<int, ChurchService> */
    public function selectableServices(?User $user): Collection
    {
        if (! ChurchService::tableReady() || ! $user) {
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

    public function autoSelectSingleService(User $user): ?ChurchService
    {
        if ($this->currentService($user)) {
            return $this->currentService($user);
        }

        $selectable = $this->selectableServices($user);
        if ($selectable->count() !== 1) {
            return null;
        }

        $service = $selectable->first();
        $this->setCurrentService($user, $service);

        return $service;
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

        $current = $this->currentService($user);
        if ($current && $selectable->contains('service_id', $current->service_id)) {
            return $current;
        }

        return $selectable->first();
    }

    public function syncFromRoute(User $user, mixed $serviceParam): void
    {
        if ($serviceParam instanceof ChurchService) {
            if ($this->userCanSelectService($user, $serviceParam)) {
                SessionStore::put(self::SESSION_KEY, $serviceParam->service_id);
            }

            return;
        }

        $service = null;

        if (is_numeric($serviceParam) && ctype_digit((string) $serviceParam)) {
            $service = ChurchService::find((int) $serviceParam);
        } elseif (is_string($serviceParam) && $serviceParam !== '' && Schema::hasColumn('service', 'slug')) {
            $service = ChurchService::query()->where('slug', $serviceParam)->first();
        }

        if ($service && $this->userCanSelectService($user, $service)) {
            SessionStore::put(self::SESSION_KEY, $service->service_id);
        }
    }
}

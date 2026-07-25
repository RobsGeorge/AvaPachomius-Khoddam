<?php

namespace App\Services\Structure;

use App\Models\ChurchService;
use App\Models\Enrollment;
use App\Models\UserServiceRole;
use App\Support\Structure\ProgressionPolicy;
use App\Support\Structure\RosterStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * T9a — who may appear in an End-of-Cycle proposal (T9b applies the moves).
 * Inactive / left / pastoral_hold / archived never auto-propose.
 */
class CycleProgressionEligibility
{
    public function __construct(
        private StructureAnchorResolver $resolver,
    ) {}

    public function serviceSupportsWizard(ChurchService $service): bool
    {
        $policy = $this->resolver->progressionPolicy($service);

        return $policy !== null && ProgressionPolicy::usesEndOfCycleWizard($policy);
    }

    public function enrollmentEligibleForPropose(Enrollment $enrollment): bool
    {
        return RosterStatus::isEligibleForPropose($enrollment->status);
    }

    public function membershipEligibleForPropose(UserServiceRole $membership): bool
    {
        if (! Schema::hasColumn($membership->getTable(), 'roster_status')) {
            return true;
        }

        return RosterStatus::isEligibleForPropose($membership->roster_status);
    }

    /**
     * Active enrollments for a service that would be proposed at cycle end.
     *
     * @return Collection<int, Enrollment>
     */
    public function proposeEligibleEnrollments(ChurchService $service): Collection
    {
        if (! Schema::hasTable('enrollments') || ! $this->serviceSupportsWizard($service)) {
            return collect();
        }

        return Enrollment::query()
            ->where(function ($q) use ($service) {
                $q->whereHas('serviceUnit', fn ($u) => $u->where('service_id', $service->service_id))
                    ->orWhereHas('course', fn ($c) => $c->where('service_id', $service->service_id));
            })
            ->where('status', RosterStatus::ACTIVE)
            ->get();
    }
}

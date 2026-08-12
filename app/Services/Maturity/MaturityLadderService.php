<?php

namespace App\Services\Maturity;

use App\Models\Church;
use App\Models\ConsentLog;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Maturity ladder rungs (ADR §17):
 * 0 record-only · 1 managed via guardian session · 2 child-held+linked · 3 emancipated.
 */
final class MaturityLadderService
{
    public const RUNG_RECORD_ONLY = 0;

    public const RUNG_MANAGED = 1;

    public const RUNG_CHILD_HELD = 2;

    public const RUNG_EMANCIPATED = 3;

    public function __construct(
        private AgePolicyResolver $agePolicies,
        private ConsentLogRepository $consents,
    ) {}

    public function ageYears(?Person $person, ?Carbon $on = null): ?int
    {
        if (! $person?->date_of_birth) {
            return null;
        }

        $on ??= Carbon::today();

        return $person->date_of_birth->age; // Carbon age as of "now"; freeze time in tests
    }

    /**
     * @param  array{digital_consent_age: int, age_of_majority: int}|null  $policy
     */
    public function isBelowDigitalConsent(Person $person, ?Church $church = null, ?array $policy = null): bool
    {
        $policy ??= $this->agePolicies->forChurch($church ?? $this->churchFor($person));
        $age = $this->ageYears($person);

        return $age !== null && $age < (int) $policy['digital_consent_age'];
    }

    /**
     * @param  array{digital_consent_age: int, age_of_majority: int}|null  $policy
     */
    public function hasReachedMajority(Person $person, ?Church $church = null, ?array $policy = null): bool
    {
        $policy ??= $this->agePolicies->forChurch($church ?? $this->churchFor($person));
        $age = $this->ageYears($person);

        return $age !== null && $age >= (int) $policy['age_of_majority'];
    }

    /**
     * @param  array{digital_consent_age: int, age_of_majority: int}|null  $policy
     */
    public function hasReachedDigitalConsent(Person $person, ?Church $church = null, ?array $policy = null): bool
    {
        $policy ??= $this->agePolicies->forChurch($church ?? $this->churchFor($person));
        $age = $this->ageYears($person);

        return $age !== null && $age >= (int) $policy['digital_consent_age'];
    }

    public function activeGuardianEdges(Person $ward): \Illuminate\Support\Collection
    {
        return Relationship::withoutTenancy()
            ->guardianOf()
            ->active()
            ->where('related_person_id', $ward->person_id)
            ->get();
    }

    public function hasActiveGuardian(Person $ward): bool
    {
        return $this->activeGuardianEdges($ward)->isNotEmpty();
    }

    public function linkedUsers(Person $person): \Illuminate\Support\Collection
    {
        return User::query()->where('person_id', $person->person_id)->get();
    }

    public function needsSelfConsent(Person $person): bool
    {
        $ended = Relationship::withoutTenancy()
            ->guardianOf()
            ->where('related_person_id', $person->person_id)
            ->whereNotNull('end_date')
            ->orderByDesc('end_date')
            ->first();

        if (! $ended) {
            return false;
        }

        return ! $this->consents->hasScope(
            $person,
            ConsentLog::SCOPE_SELF_EMANCIPATION,
            $ended->end_date?->startOfDay()
        );
    }

    public function canReviewHeldData(Person $person): bool
    {
        return $this->consents->hasScope($person, ConsentLog::SCOPE_SELF_EMANCIPATION);
    }

    public function rung(Person $person, ?Church $church = null): int
    {
        $hasUser = $this->linkedUsers($person)->isNotEmpty();
        $hasGuardian = $this->hasActiveGuardian($person);

        if ($hasGuardian && ! $hasUser) {
            return self::RUNG_MANAGED;
        }

        if ($hasGuardian && $hasUser) {
            return self::RUNG_CHILD_HELD;
        }

        if ($this->canReviewHeldData($person) || ($hasUser && ! $hasGuardian && $this->hasReachedMajority($person, $church))) {
            // Ended guardianship with self-consent, or adult with own account and no active guardian.
            if ($this->needsSelfConsent($person)) {
                // Emancipation started but blocked on re-consent — still treat as post-majority transition.
                return self::RUNG_CHILD_HELD;
            }

            return self::RUNG_EMANCIPATED;
        }

        return self::RUNG_RECORD_ONLY;
    }

    /**
     * Sync people.is_minor (and user.is_minor when present) from DOB + policy.
     */
    public function syncMinorFlags(Person $person, ?Church $church = null): Person
    {
        if (! Schema::hasColumn('people', 'is_minor')) {
            return $person;
        }

        $isMinor = ! $this->hasReachedMajority($person, $church);
        // Unknown DOB: leave is_minor as-is unless already set; default false for adults-without-DOB is conservative for records.
        if ($person->date_of_birth === null) {
            $isMinor = (bool) $person->is_minor;
        }

        if ((bool) $person->is_minor !== $isMinor) {
            $person->forceFill(['is_minor' => $isMinor])->save();
        }

        if (Schema::hasColumn('user', 'is_minor')) {
            User::query()
                ->where('person_id', $person->person_id)
                ->update(['is_minor' => $isMinor]);
        }

        return $person->fresh();
    }

    private function churchFor(Person $person): ?Church
    {
        if (! $person->church_id) {
            return Church::main();
        }

        return Church::query()->find($person->church_id) ?? Church::main();
    }
}

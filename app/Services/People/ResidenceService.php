<?php

namespace App\Services\People;

use App\Models\Church;
use App\Models\Person;
use App\Models\Residence;
use App\Models\ResidenceMember;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Residence membership is temporal and independent of the family edge graph.
 * Move-out ends membership only — never mutates relationships.
 */
final class ResidenceService
{
    /**
     * @param  array{address: string, geo?: ?string, notes?: ?string}  $attributes
     */
    public function createResidence(array $attributes, ?Church $church = null): Residence
    {
        $churchId = $church?->church_id ?? ($attributes['church_id'] ?? null);

        return Residence::withoutTenancy()->create([
            'church_id' => $churchId,
            'address' => $attributes['address'],
            'geo' => $attributes['geo'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    public function addMember(
        Residence $residence,
        Person $person,
        ?string $startDate = null,
        ?string $roleInHome = null,
    ): ResidenceMember {
        return ResidenceMember::query()->create([
            'residence_id' => $residence->residence_id,
            'person_id' => $person->person_id,
            'start_date' => $startDate ?? now()->toDateString(),
            'end_date' => null,
            'role_in_home' => $roleInHome,
        ]);
    }

    /**
     * End active membership and optionally start membership at a new residence.
     * Zero edits to relationships.
     *
     * @return array{ended: ResidenceMember, started: ?ResidenceMember}
     */
    public function moveOut(
        Person $person,
        Residence $from,
        ?Residence $to = null,
        ?string $endDate = null,
        ?string $newStartDate = null,
        ?string $roleInHome = null,
        ?User $actor = null,
    ): array {
        $membership = ResidenceMember::query()
            ->where('residence_id', $from->residence_id)
            ->where('person_id', $person->person_id)
            ->active()
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'residence' => [__('people.errors.not_active_resident')],
            ]);
        }

        return DB::transaction(function () use (
            $membership,
            $person,
            $from,
            $to,
            $endDate,
            $newStartDate,
            $roleInHome,
            $actor
        ) {
            $endedOn = $endDate ?? now()->toDateString();
            $membership->forceFill(['end_date' => $endedOn])->save();

            $started = null;
            if ($to !== null) {
                $started = $this->addMember(
                    $to,
                    $person,
                    $newStartDate ?? $endedOn,
                    $roleInHome
                );
            }

            AuditLogService::recordEvent('people.residence_move_out', [
                'person_id' => $person->person_id,
                'from_residence_id' => $from->residence_id,
                'to_residence_id' => $to?->residence_id,
                'residence_member_id' => $membership->residence_member_id,
                'end_date' => $endedOn,
                'actor_user_id' => $actor?->user_id,
            ]);

            return ['ended' => $membership->fresh(), 'started' => $started];
        });
    }
}

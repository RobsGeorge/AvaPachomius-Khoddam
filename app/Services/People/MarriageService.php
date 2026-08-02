<?php

namespace App\Services\People;

use App\Models\Church;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Validation\ValidationException;

/**
 * Marriage = insert exactly one spouse_of edge. Never re-parents existing edges.
 */
final class MarriageService
{
    public function marry(
        Person $spouseA,
        Person $spouseB,
        ?User $actor = null,
        ?Church $church = null,
        ?string $startDate = null,
    ): Relationship {
        if ((int) $spouseA->person_id === (int) $spouseB->person_id) {
            throw ValidationException::withMessages([
                'spouse' => [__('people.errors.self_marriage')],
            ]);
        }

        $churchId = $church?->church_id
            ?? $spouseA->church_id
            ?? $spouseB->church_id;

        $existing = Relationship::withoutTenancy()
            ->where('type', Relationship::TYPE_SPOUSE_OF)
            ->where(function ($q) use ($spouseA, $spouseB) {
                $q->where(function ($inner) use ($spouseA, $spouseB) {
                    $inner->where('person_id', $spouseA->person_id)
                        ->where('related_person_id', $spouseB->person_id);
                })->orWhere(function ($inner) use ($spouseA, $spouseB) {
                    $inner->where('person_id', $spouseB->person_id)
                        ->where('related_person_id', $spouseA->person_id);
                });
            })
            ->active()
            ->first();

        if ($existing) {
            return $existing;
        }

        $edge = Relationship::withoutTenancy()->create([
            'church_id' => $churchId,
            'person_id' => $spouseA->person_id,
            'related_person_id' => $spouseB->person_id,
            'type' => Relationship::TYPE_SPOUSE_OF,
            'start_date' => $startDate ?? now()->toDateString(),
            'end_date' => null,
            'verified_by' => $actor?->user_id,
        ]);

        AuditLogService::recordEvent('people.marriage', [
            'relationship_id' => $edge->relationship_id,
            'person_id' => $spouseA->person_id,
            'related_person_id' => $spouseB->person_id,
            'church_id' => $churchId,
            'actor_user_id' => $actor?->user_id,
        ]);

        return $edge;
    }
}

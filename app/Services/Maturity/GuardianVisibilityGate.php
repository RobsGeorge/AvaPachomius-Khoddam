<?php

namespace App\Services\Maturity;

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;

/**
 * Custodial/pastoral visibility wall stub (full wall lands with visits — Slice 9).
 *
 * Categories:
 * - custodial: attendance, schedule, formative grades, logistics (always allowed for active guardian)
 * - pastoral: افتقاد notes, private observations (hidden when restricted)
 * - protected: safeguarding-sensitive (hidden when restricted)
 */
final class GuardianVisibilityGate
{
    public const CATEGORY_CUSTODIAL = 'custodial';

    public const CATEGORY_PASTORAL = 'pastoral';

    public const CATEGORY_PROTECTED = 'protected';

    public function allows(Relationship $edge, string $category): bool
    {
        if ($edge->type !== Relationship::TYPE_GUARDIAN_OF || ! $edge->isActive()) {
            return false;
        }

        if ($edge->guardian_visibility === Relationship::VISIBILITY_RESTRICTED) {
            return $category === self::CATEGORY_CUSTODIAL;
        }

        return in_array($category, [
            self::CATEGORY_CUSTODIAL,
            self::CATEGORY_PASTORAL,
            self::CATEGORY_PROTECTED,
        ], true);
    }

    /**
     * Whether a guardian User may see a category for a ward Person.
     */
    public function guardianMaySee(User $guardianUser, Person $ward, string $category): bool
    {
        if (! $guardianUser->person_id) {
            return false;
        }

        $edge = Relationship::withoutTenancy()
            ->guardianOf()
            ->active()
            ->where('person_id', $guardianUser->person_id)
            ->where('related_person_id', $ward->person_id)
            ->first();

        if (! $edge) {
            return false;
        }

        return $this->allows($edge, $category);
    }
}

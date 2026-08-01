<?php

namespace App\Services\Maturity;

use App\Models\Church;
use App\Models\Person;
use App\Models\Relationship;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled emancipation: set guardian_of end_date at majority (history, not deletion).
 * No admin/guardian suppress flag — majority cannot be withheld.
 */
final class EmancipationService
{
    public function __construct(
        private AgePolicyResolver $agePolicies,
        private MaturityLadderService $ladder,
    ) {}

    /**
     * @return array{ended: int, skipped: int}
     */
    public function run(?\DateTimeInterface $asOf = null): array
    {
        $asOfDate = \Carbon\Carbon::parse($asOf ?? now())->startOfDay();
        $ended = 0;
        $skipped = 0;

        if (! Schema::hasTable('relationships') || ! Schema::hasColumn('relationships', 'end_date')) {
            return ['ended' => 0, 'skipped' => 0];
        }

        $edges = Relationship::withoutTenancy()
            ->guardianOf()
            ->active()
            ->with(['relatedPerson'])
            ->get();

        foreach ($edges as $edge) {
            /** @var Person|null $ward */
            $ward = $edge->relatedPerson;
            if (! $ward || ! $ward->date_of_birth) {
                $skipped++;
                continue;
            }

            $church = $ward->church_id
                ? Church::query()->find($ward->church_id)
                : Church::main();
            $policy = $this->agePolicies->forChurch($church);

            // Age as of $asOfDate (tests freeze Carbon::setTestNow).
            $age = $ward->date_of_birth->diffInYears($asOfDate);
            if ($age < (int) $policy['age_of_majority']) {
                $skipped++;
                continue;
            }

            $edge->forceFill(['end_date' => $asOfDate->toDateString()])->save();
            $this->ladder->syncMinorFlags($ward->fresh(), $church);
            $ended++;

            AuditLogService::recordEvent('maturity.emancipation_edge_ended', [
                'relationship_id' => $edge->relationship_id,
                'ward_person_id' => $ward->person_id,
                'guardian_person_id' => $edge->person_id,
                'age_of_majority' => (int) $policy['age_of_majority'],
                'end_date' => $asOfDate->toDateString(),
                'church_id' => $ward->church_id,
            ]);
        }

        return ['ended' => $ended, 'skipped' => $skipped];
    }
}

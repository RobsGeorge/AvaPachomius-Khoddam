<?php

namespace App\Services\Maturity;

use App\Models\ConsentLog;
use App\Models\Person;
use LogicException;

/**
 * Append-only consent_log writer (DPIA artifact).
 */
final class ConsentLogRepository
{
    public function append(
        Person $subject,
        Person $consentedBy,
        string $scope,
        ?int $churchId = null,
    ): ConsentLog {
        if (! in_array($scope, config('maturity.consent_scopes', []), true)
            && ! in_array($scope, [
                ConsentLog::SCOPE_GUARDIAN_CUSTODY,
                ConsentLog::SCOPE_RUNG2_CREDENTIAL,
                ConsentLog::SCOPE_SELF_EMANCIPATION,
            ], true)) {
            throw new LogicException("Unknown consent scope: {$scope}");
        }

        return ConsentLog::withoutTenancy()->create([
            'church_id' => $churchId ?? $subject->church_id,
            'person_id' => $subject->person_id,
            'consented_by' => $consentedBy->person_id,
            'scope' => $scope,
            'created_at' => now(),
        ]);
    }

    public function update(ConsentLog $entry, array $attributes = []): never
    {
        throw new LogicException('ConsentLogRepository refuses updates; append a new row instead.');
    }

    public function delete(ConsentLog $entry): never
    {
        throw new LogicException('ConsentLogRepository refuses deletes; consent_log is append-only.');
    }

    public function hasScope(Person $subject, string $scope, ?\DateTimeInterface $since = null): bool
    {
        $query = ConsentLog::withoutTenancy()
            ->where('person_id', $subject->person_id)
            ->where('scope', $scope);

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        return $query->exists();
    }
}

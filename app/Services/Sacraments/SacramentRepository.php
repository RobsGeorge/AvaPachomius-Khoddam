<?php

namespace App\Services\Sacraments;

use App\Models\Person;
use App\Models\Relationship;
use App\Models\Sacrament;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Immutable sacrament registrar. Exposes only record() and correct().
 * There is deliberately no update() or delete() method on this class.
 *
 * Non-goals on repose (explicit): never delete the person row; never set
 * relationships.end_date on non-guardian edges — "child of the late Y" stays true.
 */
final class SacramentRepository
{
    /**
     * @param  array{
     *     church_id: int,
     *     person_id: int,
     *     type: string,
     *     date: string|\DateTimeInterface,
     *     date_precision: string,
     *     recorded_by: int,
     *     location_church_id?: int|null,
     *     location_text?: string|null,
     *     officiant_person_id?: int|null,
     *     second_person_id?: int|null,
     *     certificate_document_id?: int|null,
     *     recorded_at?: string|\DateTimeInterface|null
     * }  $data
     */
    public function record(array $data): Sacrament
    {
        return $this->insertRow($data, null);
    }

    /**
     * @param  array{
     *     church_id?: int,
     *     person_id?: int,
     *     type?: string,
     *     date?: string|\DateTimeInterface,
     *     date_precision?: string,
     *     recorded_by: int,
     *     location_church_id?: int|null,
     *     location_text?: string|null,
     *     officiant_person_id?: int|null,
     *     second_person_id?: int|null,
     *     certificate_document_id?: int|null,
     *     recorded_at?: string|\DateTimeInterface|null
     * }  $data
     */
    public function correct(int $originalId, array $data): Sacrament
    {
        $original = Sacrament::query()->whereKey($originalId)->first();
        if ($original === null) {
            throw new InvalidArgumentException("Sacrament {$originalId} not found.");
        }

        $merged = [
            'church_id' => $data['church_id'] ?? (int) $original->church_id,
            'person_id' => $data['person_id'] ?? (int) $original->person_id,
            'type' => $data['type'] ?? (string) $original->type,
            'date' => $data['date'] ?? $original->date,
            'date_precision' => $data['date_precision'] ?? (string) $original->date_precision,
            'location_church_id' => array_key_exists('location_church_id', $data)
                ? $data['location_church_id']
                : $original->location_church_id,
            'location_text' => array_key_exists('location_text', $data)
                ? $data['location_text']
                : $original->location_text,
            'officiant_person_id' => array_key_exists('officiant_person_id', $data)
                ? $data['officiant_person_id']
                : $original->officiant_person_id,
            'second_person_id' => array_key_exists('second_person_id', $data)
                ? $data['second_person_id']
                : $original->second_person_id,
            'certificate_document_id' => array_key_exists('certificate_document_id', $data)
                ? $data['certificate_document_id']
                : $original->certificate_document_id,
            'recorded_by' => (int) $data['recorded_by'],
            'recorded_at' => $data['recorded_at'] ?? now(),
        ];

        return $this->insertRow($merged, (int) $original->sacrament_id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insertRow(array $data, ?int $correctsSacramentId): Sacrament
    {
        $type = (string) ($data['type'] ?? '');
        if (! in_array($type, Sacrament::TYPES, true)) {
            throw new InvalidArgumentException("Invalid sacrament type: {$type}");
        }

        $precision = (string) ($data['date_precision'] ?? '');
        if (! in_array($precision, Sacrament::PRECISIONS, true)) {
            throw new InvalidArgumentException("Invalid date_precision: {$precision}");
        }

        $personId = (int) ($data['person_id'] ?? 0);
        if ($personId < 1) {
            throw new InvalidArgumentException('person_id is required.');
        }

        $recordedBy = (int) ($data['recorded_by'] ?? 0);
        if ($recordedBy < 1) {
            throw new InvalidArgumentException('recorded_by is required.');
        }

        $normalizedDate = $this->normalizeDate($data['date'] ?? null, $precision);
        $recordedAt = isset($data['recorded_at'])
            ? Carbon::parse($data['recorded_at'])
            : now();

        return DB::transaction(function () use (
            $data,
            $type,
            $precision,
            $personId,
            $recordedBy,
            $normalizedDate,
            $recordedAt,
            $correctsSacramentId
        ) {
            $attributes = [
                'church_id' => (int) $data['church_id'],
                'person_id' => $personId,
                'type' => $type,
                'date' => $normalizedDate,
                'date_precision' => $precision,
                'location_church_id' => $this->nullableInt($data['location_church_id'] ?? null),
                'location_text' => $this->nullableString($data['location_text'] ?? null),
                'officiant_person_id' => $this->nullableInt($data['officiant_person_id'] ?? null),
                'second_person_id' => $this->nullableInt($data['second_person_id'] ?? null),
                'certificate_document_id' => $this->nullableInt($data['certificate_document_id'] ?? null),
                'recorded_by' => $recordedBy,
                'recorded_at' => $recordedAt,
                'corrects_sacrament_id' => $correctsSacramentId,
                'created_at' => now(),
            ];

            // Snapshot non-guardian edges before repose side-effects (non-goal guard).
            $nonGuardianEndDatesBefore = null;
            if ($type === Sacrament::TYPE_REPOSE) {
                $nonGuardianEndDatesBefore = Relationship::query()
                    ->withoutGlobalScopes()
                    ->where(function ($q) use ($personId) {
                        $q->where('person_id', $personId)
                            ->orWhere('related_person_id', $personId);
                    })
                    ->where('type', '!=', Relationship::TYPE_GUARDIAN_OF)
                    ->pluck('end_date', 'relationship_id')
                    ->all();
            }

            $sacrament = Sacrament::query()->create($attributes);

            if ($type === Sacrament::TYPE_REPOSE) {
                $this->applyReposeSideEffects($personId, $normalizedDate, $nonGuardianEndDatesBefore ?? []);
            }

            AuditLogService::recordEvent(
                $correctsSacramentId === null ? 'sacrament.recorded' : 'sacrament.corrected',
                [
                    'sacrament_id' => (int) $sacrament->sacrament_id,
                    'person_id' => $personId,
                    'type' => $type,
                    'date_precision' => $precision,
                    'corrects_sacrament_id' => $correctsSacramentId,
                    'church_id' => (int) $sacrament->church_id,
                ]
            );

            return $sacrament->fresh();
        });
    }

    /**
     * @param  array<int|string, mixed>  $nonGuardianEndDatesBefore
     */
    private function applyReposeSideEffects(int $personId, string $reposeDate, array $nonGuardianEndDatesBefore): void
    {
        $person = Person::query()->withoutGlobalScopes()->whereKey($personId)->first();
        if ($person === null) {
            throw new RuntimeException("Person {$personId} missing after sacrament insert.");
        }

        // Set deceased_at only — never delete the person row.
        if ($person->deceased_at === null) {
            $person->forceFill([
                'deceased_at' => Carbon::parse($reposeDate)->startOfDay(),
            ])->save();
        }

        // Explicit non-goal: do not end non-guardian relationships.
        $after = Relationship::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($personId) {
                $q->where('person_id', $personId)
                    ->orWhere('related_person_id', $personId);
            })
            ->where('type', '!=', Relationship::TYPE_GUARDIAN_OF)
            ->pluck('end_date', 'relationship_id')
            ->all();

        foreach ($nonGuardianEndDatesBefore as $relationshipId => $endDateBefore) {
            $endDateAfter = $after[$relationshipId] ?? null;
            $beforeKey = $endDateBefore === null ? null : (string) $endDateBefore;
            $afterKey = $endDateAfter === null ? null : (string) $endDateAfter;
            if ($beforeKey !== $afterKey) {
                throw new RuntimeException(
                    'Repose must not mutate non-guardian relationship end_date '
                    ."(relationship_id={$relationshipId})."
                );
            }
        }

        if (Person::query()->withoutGlobalScopes()->whereKey($personId)->doesntExist()) {
            throw new RuntimeException('Repose must never delete the person row.');
        }
    }

    private function normalizeDate(mixed $date, string $precision): string
    {
        if ($date === null || $date === '') {
            throw new InvalidArgumentException('date is required.');
        }

        $parsed = Carbon::parse($date);

        return match ($precision) {
            Sacrament::PRECISION_YEAR => $parsed->format('Y-01-01'),
            Sacrament::PRECISION_MONTH => $parsed->format('Y-m-01'),
            default => $parsed->format('Y-m-d'),
        };
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}

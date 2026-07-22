<?php

namespace App\Services\People;

use App\Models\Person;
use App\Support\ArabicNameNormalizer;
use Illuminate\Support\Collection;

/**
 * Flags possible people duplicates by normalized_name (+ DOB / phone when present).
 * Callers must require explicit confirmation before inserting a new person.
 */
class PersonDuplicateDetector
{
    /**
     * @param  array{
     *     first_name?: ?string,
     *     second_name?: ?string,
     *     third_name?: ?string,
     *     display_name?: ?string,
     *     date_of_birth?: ?string,
     *     mobile_number?: ?string,
     *     church_id?: ?int
     * }  $attributes
     * @return Collection<int, Person>
     */
    public function findPossibleMatches(array $attributes, ?int $excludePersonId = null): Collection
    {
        $normalized = $this->normalizedFromAttributes($attributes);
        if ($normalized === '') {
            return collect();
        }

        // Cross-tenant escape: registry lookups during import/console may run unbound.
        $query = Person::withoutTenancy()
            ->active()
            ->where('normalized_name', $normalized);

        if (! empty($attributes['church_id'])) {
            $query->where('church_id', $attributes['church_id']);
        }

        if ($excludePersonId !== null) {
            $query->where('person_id', '!=', $excludePersonId);
        }

        // normalized_name match is enough to flag; DOB/phone strengthen the signal for UI.
        return $query->orderBy('person_id')->limit(50)->get()->values();
    }

    /**
     * Rank matches: shared DOB or phone elevates confidence for admin review.
     *
     * @return list<array{person: Person, score: int, reasons: list<string>}>
     */
    public function rankMatches(Collection $matches, array $attributes): array
    {
        $dob = $this->normalizeDate($attributes['date_of_birth'] ?? null);
        $mobile = $this->normalizePhone($attributes['mobile_number'] ?? null);

        return $matches->map(function (Person $person) use ($dob, $mobile) {
            $score = 1;
            $reasons = ['normalized_name'];

            if ($dob !== null
                && $person->date_of_birth
                && $person->date_of_birth->format('Y-m-d') === $dob) {
                $score += 2;
                $reasons[] = 'date_of_birth';
            }

            if ($mobile !== null
                && $this->normalizePhone($person->mobile_number) === $mobile) {
                $score += 2;
                $reasons[] = 'mobile_number';
            }

            return [
                'person' => $person,
                'score' => $score,
                'reasons' => $reasons,
            ];
        })->sortByDesc('score')->values()->all();
    }

    /**
     * Within an import batch, find rows that collide with each other (e.g. هالة / هاله).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{normalized_name: string, row_indexes: list<int>, sample_names: list<string>}>
     */
    public function findIntraBatchCollisions(array $rows): array
    {
        $buckets = [];

        foreach ($rows as $index => $row) {
            $normalized = $this->normalizedFromAttributes($row);
            if ($normalized === '') {
                continue;
            }
            $buckets[$normalized]['indexes'][] = $index;
            $buckets[$normalized]['names'][] = trim(implode(' ', array_filter([
                (string) ($row['first_name'] ?? ''),
                (string) ($row['second_name'] ?? ''),
                (string) ($row['third_name'] ?? ''),
                (string) ($row['display_name'] ?? ''),
            ])));
        }

        $collisions = [];
        foreach ($buckets as $normalized => $bucket) {
            $indexes = array_values(array_unique($bucket['indexes']));
            if (count($indexes) < 2) {
                continue;
            }
            $collisions[] = [
                'normalized_name' => $normalized,
                'row_indexes' => $indexes,
                'sample_names' => array_values(array_unique(array_filter($bucket['names']))),
            ];
        }

        return $collisions;
    }

    /** @param  array<string, mixed>  $attributes */
    public function normalizedFromAttributes(array $attributes): string
    {
        if (! empty($attributes['display_name'])) {
            return ArabicNameNormalizer::normalize((string) $attributes['display_name']);
        }

        return ArabicNameNormalizer::fromParts(
            $attributes['first_name'] ?? null,
            $attributes['second_name'] ?? null,
            $attributes['third_name'] ?? null
        );
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' ? $digits : null;
    }
}

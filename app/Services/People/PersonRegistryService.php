<?php

namespace App\Services\People;

use App\Models\Church;
use App\Models\Person;
use App\Models\User;
use App\Support\ArabicNameNormalizer;
use Illuminate\Support\Facades\Schema;

class PersonRegistryService
{
    public function __construct(
        private readonly PersonDuplicateDetector $duplicates
    ) {}

    /**
     * Ensure the user has an active person row and person_id link.
     * Idempotent — safe for backfill and post-create hooks.
     */
    public function ensureForUser(User $user, ?int $churchId = null): Person
    {
        if (! Schema::hasColumn('user', 'person_id') || ! Schema::hasTable('people')) {
            throw new \RuntimeException('People registry schema is not migrated.');
        }

        if ($user->person_id) {
            $existing = Person::withoutTenancy()->find($user->person_id);
            if ($existing && ! $existing->isRetired()) {
                return $existing;
            }
        }

        $churchId ??= $this->resolveChurchIdForUser($user);

        $person = Person::withoutTenancy()->create([
            'church_id' => $churchId,
            'first_name' => $user->first_name,
            'second_name' => $user->second_name,
            'third_name' => $user->third_name,
            'display_name' => User::fullNameFromParts(
                (string) $user->first_name,
                (string) $user->second_name,
                (string) $user->third_name
            ) ?: $user->email,
            'date_of_birth' => $user->date_of_birth,
            'mobile_number' => $user->mobile_number,
            'national_id' => $user->national_id,
            'email' => $user->email,
        ]);

        $user->forceFill(['person_id' => $person->person_id])->saveQuietly();

        return $person;
    }

    /**
     * Create a standalone person (no user yet), after duplicate confirmation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createPerson(array $attributes, bool $confirmedDuplicate = false): Person
    {
        if (! $confirmedDuplicate) {
            $matches = $this->duplicates->findPossibleMatches($attributes);
            if ($matches->isNotEmpty()) {
                throw new PersonDuplicateNeedsConfirmationException($matches);
            }
        }

        $churchId = $attributes['church_id'] ?? Church::main()?->church_id;

        return Person::withoutTenancy()->create([
            'church_id' => $churchId,
            'first_name' => $attributes['first_name'] ?? null,
            'second_name' => $attributes['second_name'] ?? null,
            'third_name' => $attributes['third_name'] ?? null,
            'display_name' => $attributes['display_name'] ?? null,
            'date_of_birth' => $attributes['date_of_birth'] ?? null,
            'mobile_number' => $attributes['mobile_number'] ?? null,
            'national_id' => $attributes['national_id'] ?? null,
            'email' => $attributes['email'] ?? null,
            'gender' => $attributes['gender'] ?? null,
        ]);
    }

    /**
     * Find an existing person by strong identity keys, else create.
     * Match priority: national_id → email → mobile (within church).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{person: Person, linked: bool}
     */
    public function findOrCreateByIdentity(array $attributes, bool $confirmedDuplicate = false): array
    {
        $churchId = (int) ($attributes['church_id'] ?? Church::main()?->church_id);

        $query = Person::withoutTenancy()
            ->where('church_id', $churchId)
            ->whereNull('retired_at')
            ->whereNull('merged_into_person_id');

        $person = null;
        if (filled($attributes['national_id'] ?? null)) {
            $person = (clone $query)->where('national_id', $attributes['national_id'])->first();
        }
        if (! $person && filled($attributes['email'] ?? null)) {
            $person = (clone $query)->where('email', $attributes['email'])->first();
        }
        if (! $person && filled($attributes['mobile_number'] ?? null)) {
            $person = (clone $query)->where('mobile_number', $attributes['mobile_number'])->first();
        }

        if ($person) {
            $person->fill(array_filter([
                'first_name' => $attributes['first_name'] ?? null,
                'second_name' => $attributes['second_name'] ?? null,
                'third_name' => $attributes['third_name'] ?? null,
                'display_name' => $attributes['display_name'] ?? null,
                'date_of_birth' => $attributes['date_of_birth'] ?? null,
                'mobile_number' => $attributes['mobile_number'] ?? $person->mobile_number,
                'national_id' => $attributes['national_id'] ?? $person->national_id,
                'email' => $attributes['email'] ?? $person->email,
                'gender' => $attributes['gender'] ?? $person->gender,
            ], fn ($v) => $v !== null && $v !== ''))->save();

            return ['person' => $person->fresh(), 'linked' => true];
        }

        return [
            'person' => $this->createPerson($attributes, $confirmedDuplicate),
            'linked' => false,
        ];
    }

    public function resolveChurchIdForUser(User $user): int
    {
        $membershipChurchId = $user->churchMemberships()
            ->where('status', 'active')
            ->orderBy('church_user_id')
            ->value('church_id');

        if ($membershipChurchId) {
            return (int) $membershipChurchId;
        }

        $main = Church::main();
        if (! $main) {
            throw new \RuntimeException('Tenant Zero church is missing.');
        }

        return (int) $main->church_id;
    }

    public function normalizedNameForUser(User $user): string
    {
        return ArabicNameNormalizer::fromParts(
            $user->first_name,
            $user->second_name,
            $user->third_name
        );
    }
}

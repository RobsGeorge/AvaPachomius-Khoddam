<?php

namespace App\Services\Maturity;

use App\Models\Church;
use App\Models\ConsentLog;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GuardianshipService
{
    public function __construct(
        private MaturityLadderService $ladder,
        private AgePolicyResolver $agePolicies,
        private ConsentLogRepository $consents,
    ) {}

    /**
     * Create a servant-verified guardian_of edge. Never self-asserted.
     */
    public function linkGuardian(
        Person $guardian,
        Person $ward,
        User $verifier,
        ?Church $church = null,
        bool $recordConsent = true,
    ): Relationship {
        if ((int) $guardian->person_id === (int) $ward->person_id) {
            throw ValidationException::withMessages([
                'guardian' => [__('maturity.errors.self_guardian')],
            ]);
        }

        $churchId = $church?->church_id ?? $ward->church_id ?? $guardian->church_id;

        $existing = Relationship::withoutTenancy()
            ->guardianOf()
            ->where('person_id', $guardian->person_id)
            ->where('related_person_id', $ward->person_id)
            ->first();

        if ($existing && $existing->isActive()) {
            return $existing;
        }

        if ($existing && ! $existing->isActive()) {
            // Re-open ended edge only via admin exception path is out of scope;
            // create would violate unique (person, related, type). Update end_date null is an exception.
            throw ValidationException::withMessages([
                'guardian' => [__('maturity.errors.guardian_edge_ended')],
            ]);
        }

        $edge = Relationship::withoutTenancy()->create([
            'church_id' => $churchId,
            'person_id' => $guardian->person_id,
            'related_person_id' => $ward->person_id,
            'type' => Relationship::TYPE_GUARDIAN_OF,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'verified_by' => $verifier->user_id,
            'guardian_visibility' => Relationship::VISIBILITY_FULL,
        ]);

        $this->ladder->syncMinorFlags($ward, $church);

        if ($recordConsent) {
            $this->consents->append($ward, $guardian, ConsentLog::SCOPE_GUARDIAN_CUSTODY, $churchId);
        }

        AuditLogService::recordEvent('maturity.guardian_linked', [
            'relationship_id' => $edge->relationship_id,
            'guardian_person_id' => $guardian->person_id,
            'ward_person_id' => $ward->person_id,
            'verified_by' => $verifier->user_id,
            'church_id' => $churchId,
        ]);

        return $edge;
    }

    /**
     * Safeguarding override — permission key people.guardian_visibility.manage only.
     */
    public function setGuardianVisibility(
        Relationship $edge,
        string $visibility,
        User $actor,
    ): Relationship {
        if ($edge->type !== Relationship::TYPE_GUARDIAN_OF) {
            throw ValidationException::withMessages([
                'relationship' => [__('maturity.errors.not_guardian_edge')],
            ]);
        }

        if (! in_array($visibility, config('maturity.guardian_visibility', [
            Relationship::VISIBILITY_FULL,
            Relationship::VISIBILITY_RESTRICTED,
        ]), true)) {
            throw ValidationException::withMessages([
                'guardian_visibility' => [__('maturity.errors.invalid_visibility')],
            ]);
        }

        if (! $actor->can('people.guardian_visibility.manage')) {
            throw ValidationException::withMessages([
                'guardian_visibility' => [__('maturity.errors.visibility_forbidden')],
            ]);
        }

        $from = $edge->guardian_visibility;
        $edge->forceFill(['guardian_visibility' => $visibility])->save();

        AuditLogService::recordEvent('maturity.guardian_visibility_changed', [
            'relationship_id' => $edge->relationship_id,
            'from' => $from,
            'to' => $visibility,
            'actor_user_id' => $actor->user_id,
            'ward_person_id' => $edge->related_person_id,
            'guardian_person_id' => $edge->person_id,
        ]);

        return $edge->fresh();
    }

    /**
     * Rung 1→2: guardian opens a child-held account after digital-consent age.
     *
     * @param  array{email: string, mobile_number?: string|null, password?: string|null, national_id?: string|null}  $credentials
     */
    public function openChildHeldAccount(
        Person $ward,
        User $guardianUser,
        array $credentials,
        ?Church $church = null,
    ): User {
        $church ??= Church::query()->find($ward->church_id) ?? Church::main();
        $policy = $this->agePolicies->forChurch($church);

        if (! $this->ladder->hasReachedDigitalConsent($ward, $church, $policy)) {
            throw ValidationException::withMessages([
                'ward' => [__('maturity.errors.below_digital_consent')],
            ]);
        }

        if ($this->ladder->hasReachedMajority($ward, $church, $policy)) {
            // At majority use emancipation path, not guardian-opened credential.
            throw ValidationException::withMessages([
                'ward' => [__('maturity.errors.use_emancipation')],
            ]);
        }

        if (! $guardianUser->person_id) {
            throw ValidationException::withMessages([
                'guardian' => [__('maturity.errors.guardian_no_person')],
            ]);
        }

        $edge = Relationship::withoutTenancy()
            ->guardianOf()
            ->active()
            ->where('person_id', $guardianUser->person_id)
            ->where('related_person_id', $ward->person_id)
            ->first();

        if (! $edge) {
            throw ValidationException::withMessages([
                'guardian' => [__('maturity.errors.not_active_guardian')],
            ]);
        }

        if ($this->ladder->linkedUsers($ward)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'ward' => [__('maturity.errors.already_has_account')],
            ]);
        }

        $email = (string) ($credentials['email'] ?? '');
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => [__('maturity.errors.email_required')],
            ]);
        }

        return DB::transaction(function () use ($ward, $guardianUser, $credentials, $email, $church, $edge) {
            $password = $credentials['password'] ?? Str::random(32);
            $mobile = $credentials['mobile_number'] ?? null;

            // Child credential must be unique on user; do not reuse guardian phone.
            if ($mobile === null || $mobile === '' || $mobile === $guardianUser->mobile_number) {
                // Placeholder unique mobile so shared-parent-phone collision never fires on User.
                $mobile = $this->allocatePlaceholderMobile($ward->person_id);
            }

            $nationalId = $credentials['national_id'] ?? $ward->national_id;
            if (! $nationalId) {
                $nationalId = sprintf('9%013d', $ward->person_id % 10000000000000);
            }

            $user = User::create([
                'first_name' => $ward->first_name ?? 'Child',
                'second_name' => $ward->second_name ?? '',
                'third_name' => $ward->third_name ?? '',
                'profile_photo' => '',
                'national_id' => $nationalId,
                'mobile_number' => $mobile,
                'email' => $email,
                'job' => '',
                'date_of_birth' => $ward->date_of_birth,
                'password' => Hash::make($password),
                'is_verified' => true,
                'registration_completed' => true,
                'application_status' => User::APPLICATION_STATUS_APPROVED,
                'person_id' => $ward->person_id,
            ]);

            if (Schema::hasColumn('user', 'is_minor')) {
                $user->forceFill(['is_minor' => true])->save();
            }

            $guardianPerson = Person::withoutTenancy()->findOrFail($guardianUser->person_id);
            $this->consents->append(
                $ward,
                $guardianPerson,
                ConsentLog::SCOPE_RUNG2_CREDENTIAL,
                $church?->church_id ?? $ward->church_id
            );

            $this->ladder->syncMinorFlags($ward, $church);

            AuditLogService::recordEvent('maturity.rung2_account_opened', [
                'ward_person_id' => $ward->person_id,
                'user_id' => $user->user_id,
                'guardian_user_id' => $guardianUser->user_id,
                'relationship_id' => $edge->relationship_id,
                'church_id' => $church?->church_id,
            ]);

            return $user;
        });
    }

    /**
     * Emancipation step 2: now-adult re-consents in their own name.
     */
    public function recordSelfEmancipationConsent(Person $person, User $actor): ConsentLog
    {
        if ((int) $actor->person_id !== (int) $person->person_id) {
            throw ValidationException::withMessages([
                'consent' => [__('maturity.errors.self_consent_only')],
            ]);
        }

        if (! $this->ladder->needsSelfConsent($person)) {
            throw ValidationException::withMessages([
                'consent' => [__('maturity.errors.no_pending_emancipation')],
            ]);
        }

        $entry = $this->consents->append(
            $person,
            $person,
            ConsentLog::SCOPE_SELF_EMANCIPATION,
            $person->church_id
        );

        $this->ladder->syncMinorFlags($person);

        AuditLogService::recordEvent('maturity.self_emancipation_consent', [
            'person_id' => $person->person_id,
            'consent_log_id' => $entry->consent_log_id,
            'actor_user_id' => $actor->user_id,
        ]);

        return $entry;
    }

    private function allocatePlaceholderMobile(int $personId): string
    {
        // Synthetic unique 10-digit handle; not a real phone — child uses email login until real mobile verified.
        $base = 2000000000 + ($personId % 700000000);
        $candidate = (string) $base;
        $n = 0;
        while (User::query()->where('mobile_number', $candidate)->exists() && $n < 1000) {
            $candidate = (string) ($base + $n + 1);
            $n++;
        }

        return $candidate;
    }
}

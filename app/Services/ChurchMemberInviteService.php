<?php

namespace App\Services;

use App\Billing\QuotaGuard;
use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Role;
use App\Models\User;
use App\Services\People\InvitationService;
use App\Services\People\PersonDuplicateNeedsConfirmationException;
use App\Services\People\PersonRegistryService;
use Illuminate\Validation\ValidationException;

/**
 * T4 church members: add an existing user, or invite a new email via People OTP claim.
 */
class ChurchMemberInviteService
{
    public function __construct(
        private readonly ChurchProvisioningService $provisioning,
        private readonly PersonRegistryService $people,
        private readonly InvitationService $invitations,
        private readonly QuotaGuard $quotaGuard,
    ) {}

    /**
     * @param  array{
     *   email: string,
     *   role_id?: int|null,
     *   first_name?: string|null,
     *   second_name?: string|null,
     *   third_name?: string|null,
     *   mobile_number?: string|null,
     *   send_email?: bool,
     *   send_whatsapp?: bool,
     *   invited_by_user_id?: int|null,
     *   confirm_duplicate?: bool,
     * }  $input
     * @return array{mode: 'added'|'invited', user: User, invitation?: \App\Models\Invitation}
     */
    public function addOrInvite(Church $church, array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => __('validation.email', ['attribute' => 'email']),
            ]);
        }

        if (! $this->quotaGuard->canUse($church, 'max_active_users', 1)) {
            throw ValidationException::withMessages([
                'email' => __('billing.seat_quota_exceeded'),
            ]);
        }

        $role = $this->resolveRole($church, $input['role_id'] ?? null);
        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            $already = ChurchUser::query()
                ->where('church_id', $church->church_id)
                ->where('user_id', $existing->user_id)
                ->exists();

            $this->provisioning->addMember($church, $existing, $role);
            if (! $already) {
                $this->quotaGuard->syncSeatUsage($church->fresh());
            }

            return ['mode' => 'added', 'user' => $existing->fresh()];
        }

        $firstName = trim((string) ($input['first_name'] ?? ''));
        if ($firstName === '') {
            throw ValidationException::withMessages([
                'first_name' => __('tenancy.invite_first_name_required'),
            ]);
        }

        $sendEmail = array_key_exists('send_email', $input)
            ? (bool) $input['send_email']
            : true;
        $sendWhatsapp = (bool) ($input['send_whatsapp'] ?? false);
        if (! $sendEmail && ! $sendWhatsapp) {
            $sendEmail = true;
        }

        try {
            $person = $this->people->createPerson([
                'church_id' => $church->church_id,
                'first_name' => $firstName,
                'second_name' => $input['second_name'] ?? null,
                'third_name' => $input['third_name'] ?? null,
                'email' => $email,
                'mobile_number' => $input['mobile_number'] ?? null,
            ], (bool) ($input['confirm_duplicate'] ?? false));
        } catch (PersonDuplicateNeedsConfirmationException $e) {
            throw ValidationException::withMessages([
                'email' => __('tenancy.invite_duplicate_person'),
            ]);
        }

        $result = $this->invitations->invite($person, [
            'email' => $email,
            'mobile_number' => $input['mobile_number'] ?? $person->mobile_number,
            'send_email' => $sendEmail,
            'send_whatsapp' => $sendWhatsapp,
            'intended_role_id' => $role?->role_id,
            'invited_by_user_id' => $input['invited_by_user_id'] ?? null,
        ]);

        $this->quotaGuard->syncSeatUsage($church->fresh());

        return [
            'mode' => 'invited',
            'user' => $result['user'],
            'invitation' => $result['invitation'],
        ];
    }

    private function resolveRole(Church $church, mixed $roleId): ?Role
    {
        if (empty($roleId)) {
            return null;
        }

        return Role::query()
            ->where('role_id', (int) $roleId)
            ->where('church_id', $church->church_id)
            ->whereNull('course_id')
            ->whereNull('service_id')
            ->first();
    }
}

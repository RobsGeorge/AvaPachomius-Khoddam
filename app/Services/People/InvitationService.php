<?php

namespace App\Services\People;

use App\Mail\PeopleInvitationMail;
use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\OtpCode;
use App\Models\Person;
use App\Models\PersonPlacement;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CourseRoleAssignmentService;
use App\Services\ServiceRoleAssignmentService;
use App\Support\People\PlacementMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(
        private readonly PersonRegistryService $people,
        private readonly ServiceRoleAssignmentService $serviceRoles,
        private readonly CourseRoleAssignmentService $courseRoles,
        private readonly WhatsAppInviteService $whatsapp,
    ) {}

    /**
     * Invite a person to claim a portal account. Links existing incomplete users when possible.
     *
     * @param  array{send_email?: bool, send_whatsapp?: bool, service_id?: int|null, course_id?: int|null, intended_role_id?: int|null, person_placement_id?: int|null, invited_by_user_id?: int|null}  $options
     * @return array{invitation: Invitation, plain_token: string, user: User}
     */
    public function invite(Person $person, array $options = []): array
    {
        $email = $options['email'] ?? $person->email;
        $mobile = $options['mobile_number'] ?? $person->mobile_number;
        $sendEmail = (bool) ($options['send_email'] ?? filled($email));
        $sendWhatsapp = (bool) ($options['send_whatsapp'] ?? false);

        if (! $sendEmail && ! $sendWhatsapp) {
            throw ValidationException::withMessages([
                'channels' => __('people_onboarding.invite_channel_required'),
            ]);
        }

        if ($sendEmail && ! filled($email)) {
            throw ValidationException::withMessages([
                'email' => __('people_onboarding.invite_email_required'),
            ]);
        }

        if ($sendWhatsapp && ! filled($mobile)) {
            throw ValidationException::withMessages([
                'mobile_number' => __('people_onboarding.invite_mobile_required'),
            ]);
        }

        $churchId = (int) $person->church_id;
        $church = Church::query()->findOrFail($churchId);

        return DB::transaction(function () use (
            $person, $church, $email, $mobile, $sendEmail, $sendWhatsapp, $options
        ) {
            $this->revokePendingForPerson($person);

            $user = $this->resolveOrCreatePendingUser($person, $email, $mobile);
            $this->ensureInvitedMembership($church, $user);

            $token = Invitation::issueToken();
            $invitation = Invitation::withoutTenancy()->create([
                'church_id' => $church->church_id,
                'person_id' => $person->person_id,
                'user_id' => $user->user_id,
                'email' => $email,
                'mobile_number' => $mobile,
                'send_email' => $sendEmail,
                'send_whatsapp' => $sendWhatsapp,
                'token_hash' => $token['token_hash'],
                'status' => Invitation::STATUS_PENDING,
                'expires_at' => now()->addDays(14),
                'invited_by_user_id' => $options['invited_by_user_id'] ?? null,
                'service_id' => $options['service_id'] ?? null,
                'course_id' => $options['course_id'] ?? null,
                'intended_role_id' => $options['intended_role_id'] ?? null,
                'person_placement_id' => $options['person_placement_id'] ?? null,
                'prefilled_profile' => [
                    'first_name' => $person->first_name,
                    'second_name' => $person->second_name,
                    'third_name' => $person->third_name,
                    'email' => $email,
                    'mobile_number' => $mobile,
                    'national_id' => $person->national_id,
                    'date_of_birth' => optional($person->date_of_birth)?->format('Y-m-d'),
                    'gender' => $person->gender,
                ],
            ]);

            if ($invitation->person_placement_id) {
                $placement = PersonPlacement::withoutTenancy()->find($invitation->person_placement_id);
                $placement?->markPortalPending();
            }

            $otp = $this->issueOtp($user);

            if ($sendEmail) {
                try {
                    Mail::to($email)->send(new PeopleInvitationMail(
                        $user,
                        $invitation,
                        $token['plain_token'],
                        (string) $otp
                    ));
                } catch (\Throwable $e) {
                    $invitation->forceFill(['last_email_error' => $e->getMessage()])->save();
                }
            }

            if ($sendWhatsapp) {
                $result = $this->whatsapp->sendInvite($invitation, $token['plain_token'], (string) $otp);
                if (! $result['ok']) {
                    $invitation->forceFill(['last_whatsapp_error' => $result['error'] ?? 'failed'])->save();
                }
            }

            AuditLogService::recordEvent('people.invitation_created', [
                'invitation_id' => $invitation->invitation_id,
                'person_id' => $person->person_id,
                'user_id' => $user->user_id,
                'send_email' => $sendEmail,
                'send_whatsapp' => $sendWhatsapp,
            ]);

            return [
                'invitation' => $invitation->fresh(),
                'plain_token' => $token['plain_token'],
                'user' => $user->fresh(),
            ];
        });
    }

    public function resend(Invitation $invitation): array
    {
        if ($invitation->status !== Invitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => __('people_onboarding.invite_not_pending'),
            ]);
        }

        $person = Person::withoutTenancy()->findOrFail($invitation->person_id);

        return $this->invite($person, [
            'email' => $invitation->email,
            'mobile_number' => $invitation->mobile_number,
            'send_email' => $invitation->send_email,
            'send_whatsapp' => $invitation->send_whatsapp,
            'service_id' => $invitation->service_id,
            'course_id' => $invitation->course_id,
            'intended_role_id' => $invitation->intended_role_id,
            'person_placement_id' => $invitation->person_placement_id,
            'invited_by_user_id' => $invitation->invited_by_user_id,
        ]);
    }

    public function revoke(Invitation $invitation): Invitation
    {
        $invitation->forceFill(['status' => Invitation::STATUS_REVOKED])->save();

        AuditLogService::recordEvent('people.invitation_revoked', [
            'invitation_id' => $invitation->invitation_id,
            'person_id' => $invitation->person_id,
        ]);

        return $invitation->fresh();
    }

    public function verifyOtp(Invitation $invitation, string $code): bool
    {
        if (! $invitation->isAcceptable()) {
            if ($invitation->isExpired()) {
                $invitation->forceFill(['status' => Invitation::STATUS_EXPIRED])->save();
            }

            return false;
        }

        $otp = OtpCode::find($invitation->user_id);
        if (! $otp || (string) $otp->code !== (string) $code) {
            return false;
        }

        if ($otp->expires_at && $otp->expires_at->isPast()) {
            return false;
        }

        $otp->delete();

        return true;
    }

    /**
     * Complete claim: set password, activate membership, apply placements → roles.
     */
    public function accept(Invitation $invitation, string $password, bool $emailChannel = true): User
    {
        if (! $invitation->isAcceptable()) {
            throw ValidationException::withMessages([
                'invitation' => __('people_onboarding.invite_not_acceptable'),
            ]);
        }

        return DB::transaction(function () use ($invitation, $password, $emailChannel) {
            $user = User::findOrFail($invitation->user_id);
            $user->forceFill([
                'password' => Hash::make($password),
                'registration_completed' => true,
                'is_verified' => true,
                'application_status' => User::APPLICATION_STATUS_APPROVED,
            ])->save();

            if (Schema::hasColumn('user', 'email_verified_at') && $emailChannel && filled($invitation->email)) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            if (Schema::hasColumn('user', 'mobile_verified_at') && $invitation->send_whatsapp && filled($invitation->mobile_number)) {
                $user->forceFill(['mobile_verified_at' => now()])->save();
            }

            ChurchUser::query()
                ->where('church_id', $invitation->church_id)
                ->where('user_id', $user->user_id)
                ->update(['status' => 'active', 'joined_at' => now()]);

            $this->applyMembershipFromInvitation($invitation, $user);

            $invitation->forceFill([
                'status' => Invitation::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ])->save();

            if ($invitation->person_placement_id) {
                PersonPlacement::withoutTenancy()
                    ->whereKey($invitation->person_placement_id)
                    ->update(['placement_mode' => PlacementMode::PORTAL_ACTIVE]);
            }

            AuditLogService::recordEvent('people.invitation_accepted', [
                'invitation_id' => $invitation->invitation_id,
                'person_id' => $invitation->person_id,
                'user_id' => $user->user_id,
            ]);

            return $user->fresh();
        });
    }

    private function applyMembershipFromInvitation(Invitation $invitation, User $user): void
    {
        $role = $invitation->intended_role_id
            ? Role::withoutTenancy()->find($invitation->intended_role_id)
            : null;

        if ($invitation->course_id) {
            $course = Course::withoutTenancy()->find($invitation->course_id);
            if ($course) {
                $this->serviceRoles->ensureMembershipForCourse($user, (int) $course->course_id);
                $roleId = $role?->role_id;
                if (! $roleId) {
                    $student = Role::withoutTenancy()
                        ->where('course_id', $course->course_id)
                        ->where('slug', 'student')
                        ->first();
                    $roleId = $student?->role_id;
                }
                if ($roleId) {
                    $this->courseRoles->assign($user, (int) $course->course_id, (int) $roleId, false);
                }
            }

            return;
        }

        if ($invitation->service_id) {
            $service = \App\Models\ChurchService::withoutTenancy()->find($invitation->service_id);
            if ($service) {
                $assignRole = $role && (int) ($role->service_id) === (int) $service->service_id
                    ? $role
                    : $this->serviceRoles->memberRoleFor($service);
                $this->serviceRoles->assign($user, $service, $assignRole, false, true);
            }
        }

        // Also apply any portal_pending placements for this person in the church.
        $pending = PersonPlacement::withoutTenancy()
            ->where('person_id', $invitation->person_id)
            ->where('church_id', $invitation->church_id)
            ->whereIn('placement_mode', [PlacementMode::PORTAL_PENDING, PlacementMode::INFO_ONLY])
            ->where('roster_status', 'active')
            ->get();

        foreach ($pending as $placement) {
            if ($placement->placement_mode === PlacementMode::INFO_ONLY && ! $invitation->service_id) {
                continue;
            }
            if ($placement->course_id) {
                $this->serviceRoles->ensureMembershipForCourse($user, (int) $placement->course_id);
                $roleId = $placement->intended_role_id;
                if (! $roleId) {
                    $student = Role::withoutTenancy()
                        ->where('course_id', $placement->course_id)
                        ->where('slug', 'student')
                        ->first();
                    $roleId = $student?->role_id;
                }
                if ($roleId) {
                    $this->courseRoles->assign($user, (int) $placement->course_id, (int) $roleId, false);
                }
                $placement->markPortalActive();
            } elseif ($placement->service_id) {
                $service = \App\Models\ChurchService::withoutTenancy()->find($placement->service_id);
                if ($service) {
                    $assignRole = $placement->intended_role_id
                        ? Role::withoutTenancy()->find($placement->intended_role_id)
                        : null;
                    $assignRole ??= $this->serviceRoles->memberRoleFor($service);
                    $this->serviceRoles->assign($user, $service, $assignRole, false, true);
                    $placement->markPortalActive();
                }
            }
        }
    }

    private function revokePendingForPerson(Person $person): void
    {
        Invitation::withoutTenancy()
            ->where('person_id', $person->person_id)
            ->where('status', Invitation::STATUS_PENDING)
            ->update(['status' => Invitation::STATUS_REVOKED]);
    }

    private function resolveOrCreatePendingUser(Person $person, ?string $email, ?string $mobile): User
    {
        $existing = null;

        if (filled($email)) {
            $existing = User::query()->where('email', $email)->first();
        }

        if (! $existing && filled($mobile)) {
            $existing = User::query()->where('mobile_number', $mobile)->first();
        }

        if ($existing) {
            if (! $existing->person_id) {
                $existing->forceFill(['person_id' => $person->person_id])->saveQuietly();
            } elseif ((int) $existing->person_id !== (int) $person->person_id) {
                // Prefer linking invite to the person's linked user when emails collide across people.
                $linked = User::query()->where('person_id', $person->person_id)->first();
                if ($linked) {
                    $existing = $linked;
                }
            }

            if (! $existing->registration_completed) {
                $existing->forceFill([
                    'first_name' => $person->first_name ?: $existing->first_name,
                    'second_name' => $person->second_name ?: $existing->second_name,
                    'third_name' => $person->third_name ?: $existing->third_name,
                    'email' => $email ?: $existing->email,
                    'mobile_number' => $mobile ?: $existing->mobile_number,
                ])->save();
            }

            return $existing;
        }

        $linked = User::query()->where('person_id', $person->person_id)->first();
        if ($linked) {
            return $linked;
        }

        $placeholderNationalId = $person->national_id
            ?: ('INV'.str_pad((string) $person->person_id, 11, '0', STR_PAD_LEFT));
        $placeholderMobile = $mobile
            ?: ('019'.str_pad((string) ($person->person_id % 100000000), 8, '0', STR_PAD_LEFT));
        $placeholderEmail = $email
            ?: ('invite-person-'.$person->person_id.'@pending.local');

        // Ensure unique mobile/email/national_id
        if (User::where('mobile_number', $placeholderMobile)->exists()) {
            $placeholderMobile = '019'.Str::padLeft((string) random_int(0, 99999999), 8, '0');
        }
        if (User::where('email', $placeholderEmail)->exists()) {
            $placeholderEmail = 'invite-'.Str::lower(Str::random(10)).'@pending.local';
        }
        if (User::where('national_id', $placeholderNationalId)->exists()) {
            $placeholderNationalId = 'INV'.Str::upper(Str::random(11));
        }

        return User::create([
            'first_name' => $person->first_name ?: 'Invited',
            'second_name' => $person->second_name ?: 'Person',
            'third_name' => $person->third_name ?: '',
            'profile_photo' => '',
            'national_id' => $placeholderNationalId,
            'mobile_number' => $placeholderMobile,
            'email' => $placeholderEmail,
            'job' => $person->gender ? '' : '',
            'date_of_birth' => $person->date_of_birth?->format('Y-m-d') ?: '2000-01-01',
            'password' => Hash::make(Str::random(32)),
            'is_verified' => false,
            'is_superadmin' => false,
            'registration_completed' => false,
            'person_id' => $person->person_id,
        ]);
    }

    private function ensureInvitedMembership(Church $church, User $user): void
    {
        $membership = ChurchUser::firstOrCreate(
            ['church_id' => $church->church_id, 'user_id' => $user->user_id],
            ['status' => 'invited', 'joined_at' => now()]
        );

        if ($membership->status === 'active') {
            return;
        }

        if ($membership->status !== 'invited') {
            $membership->update(['status' => 'invited']);
        }
    }

    private function issueOtp(User $user): int
    {
        $otp = random_int(100000, 999999);
        OtpCode::updateOrCreate(
            ['user_id' => $user->user_id],
            ['code' => $otp, 'expires_at' => now()->addMinutes(30)]
        );

        return $otp;
    }
}

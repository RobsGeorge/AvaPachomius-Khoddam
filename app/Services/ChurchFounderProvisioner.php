<?php

namespace App\Services;

use App\Billing\ChurchSubscriptionService;
use App\Mail\ChurchFounderReadyMail;
use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\ChurchSubscription;
use App\Models\User;
use App\Support\ChurchHost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * F-22 S1 — verify → founder user + trial church (flag-gated).
 */
class ChurchFounderProvisioner
{
    public function __construct(
        private ChurchProvisioningService $provisioning,
        private ChurchSlugSuggester $slugs,
        private ChurchSubscriptionService $subscriptions,
        private ChurchApplicationMailService $mail,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('church_signup.enabled', false);
    }

    /** @return list<string> */
    public static function capabilitiesFor(string $accountKind): array
    {
        $map = (array) config('church_signup.capabilities', []);

        return array_values(array_filter(
            (array) ($map[$accountKind] ?? $map[Church::ACCOUNT_KIND_PARISH] ?? [])
        ));
    }

    public static function trialDaysFor(string $accountKind): int
    {
        $days = (array) config('church_signup.trial_days', []);
        $value = (int) ($days[$accountKind] ?? 7);

        return max(1, $value);
    }

    public function emailHasActiveSignup(string $email, ?int $exceptApplicationId = null): bool
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return false;
        }

        $query = ChurchApplication::query()
            ->whereRaw('lower(contact_email) = ?', [$normalized]);

        if ($exceptApplicationId) {
            $query->where('church_application_id', '!=', $exceptApplicationId);
        }

        foreach ($query->get() as $row) {
            if (in_array($row->status, [
                ChurchApplication::STATUS_UNVERIFIED,
                ChurchApplication::STATUS_PENDING,
            ], true)) {
                return true;
            }

            if ($row->church_id && $this->churchHasOpenTrial((int) $row->church_id)) {
                return true;
            }
        }

        return false;
    }

    public function provision(ChurchApplication $application): Church
    {
        if ($application->church_id) {
            $existing = Church::query()->find($application->church_id);
            if ($existing) {
                return $existing;
            }
        }

        $createdUser = false;
        $founder = null;

        $church = DB::transaction(function () use ($application, &$createdUser, &$founder) {
            $fresh = ChurchApplication::query()
                ->where('church_application_id', $application->church_application_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->church_id) {
                $existing = Church::query()->find($fresh->church_id);
                if ($existing) {
                    return $existing;
                }
            }

            if ($this->emailHasActiveSignup((string) $fresh->contact_email, (int) $fresh->church_application_id)) {
                throw ValidationException::withMessages([
                    'contact_email' => __('church_applications.one_trial_per_email'),
                ]);
            }

            $accountKind = $this->normalizeAccountKind($fresh->account_kind);
            $slug = $this->slugs->firstAvailable([
                'short_name' => $fresh->requested_short_name,
                'name' => $fresh->requested_name,
                'place_country_code' => $fresh->place_country_code,
                'place_governorate' => $fresh->place_governorate,
                'place_district' => $fresh->place_district,
            ]);

            [$founder, $createdUser] = $this->resolveOrCreateFounder($fresh);

            $church = $this->provisioning->create([
                'slug' => $slug,
                'name' => $fresh->requested_name,
                'short_name' => $fresh->requested_short_name,
                'account_kind' => $accountKind,
                'capabilities' => self::capabilitiesFor($accountKind),
                'place_district' => $fresh->place_district,
                'place_governorate' => $fresh->place_governorate,
                'place_country_code' => $fresh->place_country_code,
            ], [$founder->user_id]);

            $trialEndsAt = now()->addDays(self::trialDaysFor($accountKind));
            $this->subscriptions->startUnmanagedTrial($church->fresh(), $trialEndsAt);

            $settings = is_array($church->settings) ? $church->settings : [];
            $settings['self_serve'] = [
                'trial_ends_at' => $trialEndsAt->toIso8601String(),
                'account_kind' => $accountKind,
            ];
            $church->update(['settings' => $settings]);

            $fresh->update([
                'status' => ChurchApplication::STATUS_APPROVED,
                'church_id' => $church->church_id,
                'account_kind' => $accountKind,
                'email_verified_at' => $fresh->email_verified_at ?? now(),
                'reviewed_at' => now(),
                'admin_note' => $fresh->admin_note ?: 'self_serve_provision',
            ]);

            AuditLogService::recordEvent('church_application.self_serve_provisioned', [
                'church_application_id' => $fresh->church_application_id,
                'church_id' => $church->church_id,
                'slug' => $church->slug,
                'account_kind' => $accountKind,
                'founder_user_id' => $founder->user_id,
                'founder_created' => $createdUser,
            ]);

            return $church->fresh();
        });

        $application->refresh();

        if ($founder instanceof User) {
            $this->notifyFounder($founder, $church, $application, $createdUser);
        }
        $this->mail->notifySuperadminsOfProvision($application->fresh(), $church);

        return $church;
    }

    private function churchHasOpenTrial(int $churchId): bool
    {
        $church = Church::query()->find($churchId);
        if (! $church) {
            return false;
        }

        if (Schema::hasTable('church_subscription')) {
            $sub = ChurchSubscription::query()->where('church_id', $churchId)->first();
            if ($sub && $sub->status === 'trialing' && $sub->current_period_end && $sub->current_period_end->isFuture()) {
                return true;
            }
        }

        $ends = data_get($church->settings, 'self_serve.trial_ends_at');
        if (is_string($ends) && $ends !== '') {
            try {
                return now()->lt(\Illuminate\Support\Carbon::parse($ends));
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    private function normalizeAccountKind(?string $kind): string
    {
        if ($kind === Church::ACCOUNT_KIND_ONE_SERVICE) {
            return Church::ACCOUNT_KIND_ONE_SERVICE;
        }

        return Church::ACCOUNT_KIND_PARISH;
    }

    /** @return array{0: User, 1: bool} */
    private function resolveOrCreateFounder(ChurchApplication $application): array
    {
        $email = strtolower(trim((string) $application->contact_email));
        $existing = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($existing) {
            if (Schema::hasColumn('user', 'is_verified') && ! $existing->is_verified) {
                $existing->is_verified = true;
            }
            if (Schema::hasColumn('user', 'registration_completed')) {
                $existing->registration_completed = true;
            }
            if (Schema::hasColumn('user', 'application_status')) {
                $existing->application_status = User::APPLICATION_STATUS_APPROVED;
            }
            if (Schema::hasColumn('user', 'email_verified_at') && $existing->email_verified_at === null) {
                $existing->email_verified_at = now();
            }
            if (Schema::hasColumn('user', 'registration_lane') && ! $existing->registration_lane) {
                $existing->registration_lane = User::REGISTRATION_LANE_CHURCH_FOUNDER;
            }
            $existing->save();

            return [$existing->fresh(), false];
        }

        [$first, $second, $third] = $this->splitContactName((string) $application->contact_name);

        $attrs = [
            'first_name' => $first,
            'second_name' => $second,
            'third_name' => $third,
            'profile_photo' => '',
            'national_id' => $this->uniqueNationalId((int) $application->church_application_id),
            'mobile_number' => $this->uniqueMobile((string) $application->contact_mobile),
            'email' => $email,
            'job' => 'Founder',
            'date_of_birth' => '1970-01-01',
            'password' => Hash::make(Str::random(40)),
            'is_verified' => true,
            'is_superadmin' => false,
            'registration_completed' => true,
            'application_status' => User::APPLICATION_STATUS_APPROVED,
        ];

        if (Schema::hasColumn('user', 'registration_lane')) {
            $attrs['registration_lane'] = User::REGISTRATION_LANE_CHURCH_FOUNDER;
        }
        if (Schema::hasColumn('user', 'email_verified_at')) {
            $attrs['email_verified_at'] = now();
        }

        $user = User::create($attrs);

        return [$user, true];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function splitContactName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr((string) ($parts[0] ?? 'Founder'), 0, 30);
        $second = mb_substr((string) ($parts[1] ?? 'Admin'), 0, 30);
        $rest = implode(' ', array_slice($parts, 2));
        $third = mb_substr($rest !== '' ? $rest : 'Account', 0, 30);

        return [
            $first !== '' ? $first : 'Founder',
            $second !== '' ? $second : 'Admin',
            $third !== '' ? $third : 'Account',
        ];
    }

    private function uniqueNationalId(int $applicationId): string
    {
        $candidate = '9'.str_pad((string) ($applicationId % 10000000000000), 13, '0', STR_PAD_LEFT);
        if (! User::query()->where('national_id', $candidate)->exists()) {
            return $candidate;
        }

        do {
            $candidate = '9'.str_pad((string) random_int(0, 9999999999999), 13, '0', STR_PAD_LEFT);
        } while (User::query()->where('national_id', $candidate)->exists());

        return $candidate;
    }

    private function uniqueMobile(string $requested): string
    {
        $digits = preg_replace('/\D+/', '', $requested) ?? '';
        if (strlen($digits) >= 8 && strlen($digits) <= 15
            && ! User::query()->where('mobile_number', $digits)->exists()) {
            return $digits;
        }

        do {
            $candidate = '018'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (User::query()->where('mobile_number', $candidate)->exists());

        return $candidate;
    }

    private function notifyFounder(User $founder, Church $church, ChurchApplication $application, bool $createdUser): void
    {
        $loginUrl = ChurchHost::url($church, '/login');
        $churchUrl = ChurchHost::url($church, '/dashboard');

        if ($createdUser) {
            $status = Password::sendResetLink(['email' => $founder->email]);
            if ($status === Password::RESET_LINK_SENT) {
                return;
            }
            Log::warning('Church founder password reset email failed', [
                'user_id' => $founder->user_id,
                'status' => $status,
            ]);
        }

        try {
            Mail::to($founder->email)->send(new ChurchFounderReadyMail(
                $founder,
                $church,
                $application,
                $loginUrl,
                $churchUrl,
            ));
        } catch (\Throwable $e) {
            Log::warning('Church founder ready email failed', [
                'user_id' => $founder->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

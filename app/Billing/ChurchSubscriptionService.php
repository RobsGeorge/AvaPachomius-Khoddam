<?php

namespace App\Billing;

use App\Models\BillingAccount;
use App\Models\Church;
use App\Models\ChurchEntitlementOverride;
use App\Models\ChurchSubscription;
use App\Models\Organization;
use App\Models\PlanEntitlement;
use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChurchSubscriptionService
{
    public function __construct(
        private EntitlementSyncService $sync,
        private QuotaGuard $quotaGuard,
    ) {}

    public function assignPlan(
        Church $church,
        SubscriptionPlan $plan,
        ?PlanPrice $price = null,
        string $status = 'active',
        ?User $actor = null,
        ?string $compReason = null,
    ): ChurchSubscription {
        if (! $plan->isActive() && $status !== 'comped') {
            throw ValidationException::withMessages([
                'plan_id' => [__('billing.plan_not_active')],
            ]);
        }

        if ($price && $price->plan_id !== $plan->plan_id) {
            throw ValidationException::withMessages([
                'plan_price_id' => [__('billing.price_plan_mismatch')],
            ]);
        }

        return DB::transaction(function () use ($church, $plan, $price, $status, $actor, $compReason) {
            $billingAccount = $this->ensureBillingAccount($church);

            $subscription = ChurchSubscription::updateOrCreate(
                ['church_id' => $church->church_id],
                [
                    'plan_id' => $plan->plan_id,
                    'plan_price_id' => $price?->plan_price_id,
                    'billing_account_id' => $billingAccount->billing_account_id,
                    'status' => $status,
                    'seat_count_purchased' => $plan->includes_seats,
                    'comped_by_user_id' => $status === 'comped' ? $actor?->user_id : null,
                    'comp_reason' => $status === 'comped' ? $compReason : null,
                    'current_period_start' => now(),
                    'current_period_end' => $price && $price->billing_interval === 'year'
                        ? now()->addYear()
                        : now()->addMonth(),
                ]
            );

            $this->sync->sync($church->fresh());
            $this->quotaGuard->syncSeatUsage($church->fresh());

            AuditLogService::recordEvent('billing.plan_assigned', [
                'church_id' => $church->church_id,
                'plan_id' => $plan->plan_id,
                'plan_slug' => $plan->slug,
                'status' => $status,
            ]);

            return $subscription->fresh(['plan', 'planPrice']);
        });
    }

    public function compChurch(Church $church, ?SubscriptionPlan $plan = null, ?User $actor = null, ?string $reason = null): ChurchSubscription
    {
        $plan ??= SubscriptionPlan::query()->where('slug', 'enterprise')->first()
            ?? SubscriptionPlan::query()->where('status', 'active')->orderByDesc('tier_rank')->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'plan' => [__('billing.no_plan_available')],
            ]);
        }

        return $this->assignPlan($church, $plan, null, 'comped', $actor, $reason ?? 'comped');
    }

    public function setOverride(
        Church $church,
        string $featureKey,
        mixed $value,
        ?User $actor = null,
        ?string $reason = null,
        ?\DateTimeInterface $expiresAt = null,
    ): ChurchEntitlementOverride {
        $this->assertValidFeatureKey($featureKey);
        $this->assertValidFeatureValue($featureKey, $value);

        $override = ChurchEntitlementOverride::updateOrCreate(
            ['church_id' => $church->church_id, 'feature_key' => $featureKey],
            [
                'value' => PlanEntitlement::wrapValue($value),
                'reason' => $reason,
                'granted_by_user_id' => $actor?->user_id,
                'expires_at' => $expiresAt,
            ]
        );

        $this->sync->sync($church->fresh());

        AuditLogService::recordEvent('billing.entitlement_override_set', [
            'church_id' => $church->church_id,
            'feature_key' => $featureKey,
            'value' => $value,
        ]);

        return $override;
    }

    public function removeOverride(Church $church, string $featureKey): void
    {
        ChurchEntitlementOverride::query()
            ->where('church_id', $church->church_id)
            ->where('feature_key', $featureKey)
            ->delete();

        $this->sync->sync($church->fresh());

        AuditLogService::recordEvent('billing.entitlement_override_removed', [
            'church_id' => $church->church_id,
            'feature_key' => $featureKey,
        ]);
    }

    public function ensureTenantZeroComped(): void
    {
        $main = Church::main();
        if (! ChurchSubscription::where('church_id', $main->church_id)->exists()) {
            $this->compChurch($main, null, null, 'tenant_zero');
        }
    }

    private function ensureBillingAccount(Church $church): BillingAccount
    {
        $orgId = $church->organization_id;
        if (! $orgId) {
            app(\App\Services\ChurchProvisioningService::class)->ensureOrganizationLinked($church->fresh());
            $orgId = $church->fresh()->organization_id;
        }

        if (! $orgId) {
            throw ValidationException::withMessages([
                'organization' => [__('billing.missing_organization')],
            ]);
        }

        return BillingAccount::firstOrCreate(
            ['organization_id' => $orgId],
            ['default_currency' => config('billing.default_currency', 'EGP')]
        );
    }

    private function assertValidFeatureKey(string $featureKey): void
    {
        if (! array_key_exists($featureKey, (array) config('platform_entitlements'))) {
            throw ValidationException::withMessages([
                'feature_key' => [__('billing.unknown_feature', ['key' => $featureKey])],
            ]);
        }
    }

    private function assertValidFeatureValue(string $featureKey, mixed $value): void
    {
        $def = config("platform_entitlements.{$featureKey}");
        if (! $def) {
            return;
        }

        match ($def['type']) {
            'boolean' => $this->assertBooleanValue($value),
            'limit' => $this->assertLimitValue($value),
            'enum' => $this->assertEnumValue($def, $value),
            default => null,
        };
    }

    private function assertBooleanValue(mixed $value): void
    {
        if (is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            return;
        }

        throw ValidationException::withMessages(['value' => [__('billing.invalid_boolean')]]);
    }

    private function assertLimitValue(mixed $value): void
    {
        if ($value === null || is_int($value) || (is_string($value) && ctype_digit($value))) {
            return;
        }

        throw ValidationException::withMessages(['value' => [__('billing.invalid_limit')]]);
    }

    /** @param  array<string, mixed>  $def */
    private function assertEnumValue(array $def, mixed $value): void
    {
        if (in_array($value, (array) ($def['enum_options'] ?? []), true)) {
            return;
        }

        throw ValidationException::withMessages(['value' => [__('billing.invalid_enum')]]);
    }
}

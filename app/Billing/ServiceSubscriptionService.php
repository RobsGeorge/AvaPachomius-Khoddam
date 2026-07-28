<?php

namespace App\Billing;

use App\Models\BillingAccount;
use App\Models\Church;
use App\Models\ChurchService;
use App\Models\PlanEntitlement;
use App\Models\PlanPrice;
use App\Models\ServiceEntitlementOverride;
use App\Models\ServiceSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceSubscriptionService
{
    public function __construct(
        private EntitlementResolver $resolver,
        private QuotaGuard $quotaGuard,
    ) {}

    public function assignPlan(
        ChurchService $service,
        SubscriptionPlan $plan,
        ?PlanPrice $price = null,
        string $status = 'active',
        ?User $actor = null,
        ?string $compReason = null,
        bool $independentPayer = false,
    ): ServiceSubscription {
        if (! $plan->allowsService()) {
            throw ValidationException::withMessages([
                'plan_id' => [__('billing.plan_scope_service_only')],
            ]);
        }

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

        $churchId = (int) $service->church_id;
        if ($churchId < 1) {
            throw ValidationException::withMessages([
                'service' => [__('billing.service_missing_church')],
            ]);
        }

        return DB::transaction(function () use ($service, $plan, $price, $status, $actor, $compReason, $independentPayer, $churchId) {
            $billingAccountId = null;
            if ($independentPayer) {
                $billingAccountId = $this->ensureServiceBillingAccount($service)->billing_account_id;
            }

            $subscription = ServiceSubscription::updateOrCreate(
                ['service_id' => $service->service_id],
                [
                    'church_id' => $churchId,
                    'plan_id' => $plan->plan_id,
                    'plan_price_id' => $price?->plan_price_id,
                    'billing_account_id' => $billingAccountId,
                    'status' => $status,
                    'comped_by_user_id' => $status === 'comped' ? $actor?->user_id : null,
                    'comp_reason' => $status === 'comped' ? $compReason : null,
                    'current_period_start' => now(),
                    'current_period_end' => $price && $price->billing_interval === 'year'
                        ? now()->addYear()
                        : now()->addMonth(),
                ]
            );

            $this->resolver->resolveForService($service->fresh(), true);

            AuditLogService::recordEvent('billing.service_plan_assigned', [
                'service_id' => $service->service_id,
                'church_id' => $churchId,
                'plan_id' => $plan->plan_id,
                'plan_slug' => $plan->slug,
                'status' => $status,
                'independent_payer' => $independentPayer,
            ]);

            return $subscription->fresh(['plan', 'planPrice', 'billingAccount']);
        });
    }

    public function setOverride(
        ChurchService $service,
        string $featureKey,
        mixed $value,
        ?User $actor = null,
        ?string $reason = null,
        ?\DateTimeInterface $expiresAt = null,
    ): ServiceEntitlementOverride {
        $this->assertValidFeatureKey($featureKey);

        $override = ServiceEntitlementOverride::updateOrCreate(
            ['service_id' => $service->service_id, 'feature_key' => $featureKey],
            [
                'value' => PlanEntitlement::wrapValue($value),
                'reason' => $reason,
                'granted_by_user_id' => $actor?->user_id,
                'expires_at' => $expiresAt,
            ]
        );

        $this->resolver->resolveForService($service->fresh(), true);

        AuditLogService::recordEvent('billing.service_entitlement_override_set', [
            'service_id' => $service->service_id,
            'feature_key' => $featureKey,
            'value' => $value,
        ]);

        return $override;
    }

    public function removeOverride(ChurchService $service, string $featureKey): void
    {
        ServiceEntitlementOverride::query()
            ->where('service_id', $service->service_id)
            ->where('feature_key', $featureKey)
            ->delete();

        $this->resolver->resolveForService($service->fresh(), true);

        AuditLogService::recordEvent('billing.service_entitlement_override_removed', [
            'service_id' => $service->service_id,
            'feature_key' => $featureKey,
        ]);
    }

    public function ensureServiceBillingAccount(ChurchService $service): BillingAccount
    {
        $church = Church::query()->find($service->church_id);
        if (! $church) {
            throw ValidationException::withMessages([
                'service' => [__('billing.service_missing_church')],
            ]);
        }

        app(\App\Services\ChurchProvisioningService::class)->ensureOrganizationLinked($church->fresh());
        $orgId = $church->fresh()->organization_id;

        if (! $orgId) {
            throw ValidationException::withMessages([
                'organization' => [__('billing.missing_organization')],
            ]);
        }

        return BillingAccount::firstOrCreate(
            ['service_id' => $service->service_id],
            [
                'organization_id' => $orgId,
                'default_currency' => config('billing.default_currency', 'EGP'),
            ]
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
}

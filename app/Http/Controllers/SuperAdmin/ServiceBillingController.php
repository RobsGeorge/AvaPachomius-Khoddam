<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Billing\EntitlementResolver;
use App\Billing\ServiceSubscriptionService;
use App\Http\Controllers\Controller;
use App\Models\ChurchService;
use App\Models\PlanPrice;
use App\Models\PlatformFeature;
use App\Models\ServiceEntitlementOverride;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceBillingController extends Controller
{
    public function __construct(
        private ServiceSubscriptionService $subscriptions,
        private EntitlementResolver $resolver,
    ) {}

    public function show(ChurchService $service)
    {
        $service->load(['subscription.plan', 'subscription.planPrice', 'subscription.billingAccount', 'church']);

        $features = PlatformFeature::query()->where('is_active', true)->orderBy('sort_order')->get();
        $resolved = $this->resolver->resolveForService($service);
        $addons = $this->resolver->computeServiceAddons($service);
        $overrides = ServiceEntitlementOverride::query()
            ->where('service_id', $service->service_id)
            ->get();

        return view('superadmin.services.billing', [
            'service' => $service,
            'church' => $service->church,
            'plans' => SubscriptionPlan::query()
                ->where('status', 'active')
                ->whereIn('scope', ['service', 'both'])
                ->orderBy('tier_rank')
                ->get(),
            'features' => $features,
            'resolved' => $resolved,
            'addons' => $addons,
            'overrides' => $overrides,
        ]);
    }

    public function assignPlan(Request $request, ChurchService $service)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plan,plan_id'],
            'plan_price_id' => ['nullable', 'integer', 'exists:plan_price,plan_price_id'],
            'status' => ['required', Rule::in(['active', 'trialing', 'comped'])],
            'comp_reason' => ['nullable', 'string', 'max:255'],
            'independent_payer' => ['nullable', 'boolean'],
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
        $price = ! empty($validated['plan_price_id'])
            ? PlanPrice::findOrFail($validated['plan_price_id'])
            : $plan->prices()->where('is_default', true)->first();

        $this->subscriptions->assignPlan(
            $service,
            $plan,
            $price,
            $validated['status'],
            $request->user(),
            $validated['comp_reason'] ?? null,
            $request->boolean('independent_payer'),
        );

        return back()->with('success', __('billing.service_plan_assigned'));
    }

    public function storeOverride(Request $request, ChurchService $service)
    {
        $validated = $request->validate([
            'feature_key' => ['required', 'string', 'max:60'],
            'value' => ['required'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $value = $this->parseOverrideValue($validated['feature_key'], $validated['value']);

        $this->subscriptions->setOverride(
            $service,
            $validated['feature_key'],
            $value,
            $request->user(),
            $validated['reason'] ?? null,
            isset($validated['expires_at']) ? new \DateTimeImmutable($validated['expires_at']) : null,
        );

        return back()->with('success', __('billing.override_saved'));
    }

    public function destroyOverride(ChurchService $service, string $featureKey)
    {
        $this->subscriptions->removeOverride($service, $featureKey);

        return back()->with('success', __('billing.override_removed'));
    }

    private function parseOverrideValue(string $featureKey, mixed $raw): mixed
    {
        $def = config("platform_entitlements.{$featureKey}");
        if (! $def) {
            return $raw;
        }

        return match ($def['type']) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'limit' => $raw === '' ? null : (int) $raw,
            'enum' => (string) $raw,
            default => $raw,
        };
    }
}

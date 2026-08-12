<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Billing\ChurchSubscriptionService;
use App\Billing\EntitlementResolver;
use App\Billing\QuotaGuard;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchEntitlementOverride;
use App\Models\PlanPrice;
use App\Models\PlatformFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChurchBillingController extends Controller
{
    public function __construct(
        private ChurchSubscriptionService $subscriptions,
        private EntitlementResolver $resolver,
        private QuotaGuard $quotaGuard,
    ) {}

    public function show(Church $church)
    {
        $church->load(['subscription.plan', 'subscription.planPrice', 'capabilities']);

        $features = PlatformFeature::query()->where('is_active', true)->orderBy('sort_order')->get();
        $resolved = $this->resolver->resolve($church);
        $overrides = ChurchEntitlementOverride::query()
            ->where('church_id', $church->church_id)
            ->get();

        $services = \App\Models\ChurchService::query()
            ->withoutTenancy()
            ->where('church_id', $church->church_id)
            ->with(['subscription.plan'])
            ->orderBy('title')
            ->get();

        return view('superadmin.churches.billing', [
            'church' => $church,
            'plans' => SubscriptionPlan::query()
                ->where('status', 'active')
                ->whereIn('scope', ['church', 'both'])
                ->orderBy('tier_rank')
                ->get(),
            'servicePlans' => SubscriptionPlan::query()
                ->where('status', 'active')
                ->whereIn('scope', ['service', 'both'])
                ->orderBy('tier_rank')
                ->get(),
            'features' => $features,
            'resolved' => $resolved,
            'overrides' => $overrides,
            'services' => $services,
            'seatUsed' => $this->quotaGuard->used($church, 'max_active_users'),
            'seatLimit' => $this->quotaGuard->limit($church, 'max_active_users'),
            'storageUsed' => $this->quotaGuard->used($church, 'storage_bytes'),
            'storageLimit' => $this->quotaGuard->limit($church, 'storage_bytes'),
            'servicesUsed' => $this->quotaGuard->used($church, 'max_services'),
            'servicesLimit' => $this->quotaGuard->limit($church, 'max_services'),
        ]);
    }

    public function assignPlan(Request $request, Church $church)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plan,plan_id'],
            'plan_price_id' => ['nullable', 'integer', 'exists:plan_price,plan_price_id'],
            'status' => ['required', Rule::in(['active', 'trialing', 'comped'])],
            'comp_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
        if (! $plan->allowsChurch()) {
            return back()->withErrors(['plan_id' => __('billing.plan_scope_church_only')]);
        }
        $price = ! empty($validated['plan_price_id'])
            ? PlanPrice::findOrFail($validated['plan_price_id'])
            : $plan->prices()->where('is_default', true)->first();

        $this->subscriptions->assignPlan(
            $church,
            $plan,
            $price,
            $validated['status'],
            $request->user(),
            $validated['comp_reason'] ?? null,
        );

        return back()->with('success', __('billing.church_plan_assigned'));
    }

    public function storeOverride(Request $request, Church $church)
    {
        $validated = $request->validate([
            'feature_key' => ['required', 'string', 'max:60'],
            'value' => ['required'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $value = $this->parseOverrideValue($validated['feature_key'], $validated['value']);

        $this->subscriptions->setOverride(
            $church,
            $validated['feature_key'],
            $value,
            $request->user(),
            $validated['reason'] ?? null,
            isset($validated['expires_at']) ? new \DateTimeImmutable($validated['expires_at']) : null,
        );

        return back()->with('success', __('billing.override_saved'));
    }

    public function destroyOverride(Church $church, string $featureKey)
    {
        $this->subscriptions->removeOverride($church, $featureKey);

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

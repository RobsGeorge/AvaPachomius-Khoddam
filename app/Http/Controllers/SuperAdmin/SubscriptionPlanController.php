<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Billing\SubscriptionPlanService;
use App\Http\Controllers\Controller;
use App\Models\PlatformFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        private SubscriptionPlanService $plans,
    ) {}

    public function index()
    {
        $plans = SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->with('prices')
            ->orderBy('tier_rank')
            ->get();

        return view('superadmin.plans.index', [
            'plans' => $plans,
        ]);
    }

    public function create()
    {
        app(\App\Billing\PlatformFeatureCatalog::class)->syncFromConfig();

        return view('superadmin.plans.create', [
            'features' => PlatformFeature::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'catalog' => config('platform_entitlements'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePlan($request);

        $plan = $this->plans->create($validated);

        return redirect()
            ->route('superadmin.plans.show', $plan)
            ->with('success', __('billing.plan_created'));
    }

    public function show(SubscriptionPlan $plan)
    {
        $plan->load(['entitlements', 'prices', 'subscriptions.church']);

        return view('superadmin.plans.show', [
            'plan' => $plan,
            'features' => PlatformFeature::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'catalog' => config('platform_entitlements'),
        ]);
    }

    public function edit(SubscriptionPlan $plan)
    {
        app(\App\Billing\PlatformFeatureCatalog::class)->syncFromConfig();
        $plan->load(['entitlements', 'prices']);

        $entitlementMap = $plan->entitlements->mapWithKeys(
            fn ($e) => [$e->feature_key => $e->resolvedValue()]
        )->all();

        return view('superadmin.plans.edit', [
            'plan' => $plan,
            'features' => PlatformFeature::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'catalog' => config('platform_entitlements'),
            'entitlementMap' => $entitlementMap,
        ]);
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $validated = $this->validatePlan($request, $plan);

        $this->plans->update($plan, $validated);

        return redirect()
            ->route('superadmin.plans.show', $plan)
            ->with('success', __('billing.plan_updated'));
    }

    /** @return array<string, mixed> */
    private function validatePlan(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $slugRule = ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
        if ($plan) {
            $slugRule[] = Rule::unique('subscription_plan', 'slug')->ignore($plan->plan_id, 'plan_id');
        } else {
            $slugRule[] = Rule::unique('subscription_plan', 'slug');
        }

        return $request->validate([
            'slug' => $slugRule,
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tier_rank' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_public' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'includes_seats' => ['required', 'integer', 'min:1', 'max:100000'],
            'seat_overage_policy' => ['required', Rule::in(['block', 'warn', 'bill'])],
            'entitlements' => ['nullable', 'array'],
            'entitlements.*' => ['nullable'],
            'prices' => ['nullable', 'array'],
            'prices.*.billing_interval' => ['nullable', Rule::in(['month', 'year'])],
            'prices.*.amount_minor' => ['nullable', 'integer', 'min:0'],
            'prices.*.currency' => ['nullable', 'string', 'size:3'],
            'prices.*.trial_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'prices.*.is_default' => ['nullable', 'boolean'],
        ]);
    }
}

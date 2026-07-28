<?php

namespace App\Billing;

use App\Models\PlanEntitlement;
use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionPlanService
{
    /**
     * @param  array{
     *     slug?: string,
     *     name: string,
     *     description?: string|null,
     *     tier_rank?: int,
     *     is_public?: bool,
     *     status?: string,
     *     includes_seats?: int,
     *     seat_overage_policy?: string,
     *     entitlements?: array<string, mixed>,
     *     prices?: list<array{billing_interval: string, amount_minor: int, currency?: string, trial_days?: int, is_default?: bool}>
     * }  $input
     */
    public function create(array $input): SubscriptionPlan
    {
        $slug = $input['slug'] ?? Str::slug($input['name']);
        if (SubscriptionPlan::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => [__('billing.slug_taken')],
            ]);
        }

        return DB::transaction(function () use ($input, $slug) {
            $plan = SubscriptionPlan::create([
                'slug' => $slug,
                'name' => $input['name'],
                'description' => $input['description'] ?? null,
                'tier_rank' => (int) ($input['tier_rank'] ?? 0),
                'is_public' => (bool) ($input['is_public'] ?? true),
                'is_custom' => false,
                'status' => $input['status'] ?? 'draft',
                'includes_seats' => (int) ($input['includes_seats'] ?? 50),
                'seat_overage_policy' => $input['seat_overage_policy'] ?? 'block',
            ]);

            $this->syncEntitlements($plan, $input['entitlements'] ?? []);
            $this->syncPrices($plan, $input['prices'] ?? []);

            AuditLogService::recordEvent('billing.plan_created', [
                'plan_id' => $plan->plan_id,
                'slug' => $plan->slug,
            ]);

            return $plan->fresh(['entitlements', 'prices']);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(SubscriptionPlan $plan, array $input): SubscriptionPlan
    {
        return DB::transaction(function () use ($plan, $input) {
            $plan->update([
                'name' => $input['name'] ?? $plan->name,
                'description' => $input['description'] ?? $plan->description,
                'tier_rank' => (int) ($input['tier_rank'] ?? $plan->tier_rank),
                'is_public' => array_key_exists('is_public', $input) ? (bool) $input['is_public'] : $plan->is_public,
                'status' => $input['status'] ?? $plan->status,
                'includes_seats' => (int) ($input['includes_seats'] ?? $plan->includes_seats),
                'seat_overage_policy' => $input['seat_overage_policy'] ?? $plan->seat_overage_policy,
            ]);

            if (array_key_exists('entitlements', $input)) {
                $this->syncEntitlements($plan, (array) $input['entitlements']);
            }

            if (array_key_exists('prices', $input)) {
                $this->syncPrices($plan, (array) $input['prices']);
            }

            AuditLogService::recordEvent('billing.plan_updated', [
                'plan_id' => $plan->plan_id,
                'slug' => $plan->slug,
            ]);

            return $plan->fresh(['entitlements', 'prices']);
        });
    }

    /** @param  array<string, mixed>  $entitlements */
    public function syncEntitlements(SubscriptionPlan $plan, array $entitlements): void
    {
        $catalog = array_keys((array) config('platform_entitlements'));

        PlanEntitlement::query()->where('plan_id', $plan->plan_id)->delete();

        foreach ($entitlements as $featureKey => $value) {
            if (! in_array($featureKey, $catalog, true)) {
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }

            PlanEntitlement::create([
                'plan_id' => $plan->plan_id,
                'feature_key' => $featureKey,
                'value' => PlanEntitlement::wrapValue($this->castEntitlementValue($featureKey, $value)),
            ]);
        }
    }

    /** @param  list<array{billing_interval: string, amount_minor: int, currency?: string, trial_days?: int, is_default?: bool}>  $prices */
    public function syncPrices(SubscriptionPlan $plan, array $prices): void
    {
        foreach ($prices as $priceData) {
            if (empty($priceData['billing_interval']) || ! isset($priceData['amount_minor'])) {
                continue;
            }

            PlanPrice::updateOrCreate(
                [
                    'plan_id' => $plan->plan_id,
                    'billing_interval' => $priceData['billing_interval'],
                ],
                [
                    'amount_minor' => (int) $priceData['amount_minor'],
                    'currency' => $priceData['currency'] ?? config('billing.default_currency', 'EGP'),
                    'trial_days' => (int) ($priceData['trial_days'] ?? 0),
                    'is_default' => (bool) ($priceData['is_default'] ?? false),
                ]
            );
        }
    }

    private function castEntitlementValue(string $featureKey, mixed $value): mixed
    {
        $def = config("platform_entitlements.{$featureKey}");
        if (! $def) {
            return $value;
        }

        return match ($def['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'limit' => $value === '' || $value === null ? null : (int) $value,
            'enum' => (string) $value,
            default => $value,
        };
    }
}

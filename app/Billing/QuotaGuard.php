<?php

namespace App\Billing;

use App\Models\Church;
use App\Models\ChurchUsageCounter;
use App\Models\ChurchUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enforces numeric quotas (storage, seats, etc.) per church.
 */
class QuotaGuard
{
    public function __construct(
        private EntitlementResolver $resolver,
    ) {}

    public function used(Church $church, string $featureKey, ?string $periodKey = null): int
    {
        $periodKey ??= $this->periodKeyFor($featureKey);

        $row = ChurchUsageCounter::query()
            ->where('church_id', $church->church_id)
            ->where('feature_key', $featureKey)
            ->where('period_key', $periodKey)
            ->first();

        if ($row) {
            return (int) $row->used_amount;
        }

        if ($featureKey === 'max_active_users') {
            return $this->countActiveMembers($church);
        }

        return 0;
    }

    public function limit(Church $church, string $featureKey): ?int
    {
        if ($featureKey === 'max_active_users') {
            return $this->resolver->limitValue($church, 'max_active_users');
        }

        return $this->resolver->limitValue($church, $featureKey);
    }

    public function remaining(Church $church, string $featureKey): ?int
    {
        $limit = $this->limit($church, $featureKey);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->used($church, $featureKey));
    }

    public function canUse(Church $church, string $featureKey, int $delta = 1): bool
    {
        $limit = $this->limit($church, $featureKey);
        if ($limit === null) {
            return true;
        }

        return ($this->used($church, $featureKey) + $delta) <= $limit;
    }

    public function enforce(Church $church, string $featureKey, int $delta = 1): void
    {
        if (! $this->canUse($church, $featureKey, $delta)) {
            throw ValidationException::withMessages([
                'quota' => [__('billing.quota_exceeded', ['feature' => __("billing.features.{$featureKey}")])],
            ]);
        }
    }

    public function recordUsage(Church $church, string $featureKey, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $periodKey = $this->periodKeyFor($featureKey);

        DB::transaction(function () use ($church, $featureKey, $periodKey, $delta) {
            $row = ChurchUsageCounter::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'church_id' => $church->church_id,
                        'feature_key' => $featureKey,
                        'period_key' => $periodKey,
                    ],
                    ['used_amount' => 0]
                );

            $row->used_amount = max(0, (int) $row->used_amount + $delta);
            $row->save();
        });
    }

    public function syncSeatUsage(Church $church): int
    {
        $count = $this->countActiveMembers($church);
        $periodKey = $this->periodKeyFor('max_active_users');

        ChurchUsageCounter::updateOrCreate(
            [
                'church_id' => $church->church_id,
                'feature_key' => 'max_active_users',
                'period_key' => $periodKey,
            ],
            ['used_amount' => $count]
        );

        if ($church->subscription) {
            $church->subscription->update(['seat_count_effective' => $count]);
        }

        return $count;
    }

    public function allowsCustomDomain(Church $church): bool
    {
        return $this->resolver->booleanValue($church, 'custom_domain');
    }

    private function countActiveMembers(Church $church): int
    {
        return ChurchUser::query()
            ->where('church_id', $church->church_id)
            ->where('status', 'active')
            ->count();
    }

    private function periodKeyFor(string $featureKey): string
    {
        return (string) data_get(config('billing.quota_period_keys'), $featureKey, 'lifetime');
    }
}

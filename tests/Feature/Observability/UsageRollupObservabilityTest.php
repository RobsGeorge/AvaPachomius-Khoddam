<?php

namespace Tests\Feature\Observability;

use App\Models\UsageRollup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Support\EventModuleTestCase;

class UsageRollupObservabilityTest extends EventModuleTestCase
{
    public function test_usage_tab_shows_rollups_for_superadmin(): void
    {
        UsageRollup::withoutTenancy()->create([
            'bucket_start' => now()->startOfHour(),
            'church_id' => 1,
            'service_id' => null,
            'active_users' => 3,
            'request_count' => 42,
            'unique_sessions' => 5,
        ]);

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'usage-super@example.com',
            'registration_completed' => true,
            'is_verified' => true,
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.observability.index', ['tab' => 'usage']))
            ->assertOk()
            ->assertSee('42', false);
    }

    public function test_flush_usage_command_persists_cache_counters(): void
    {
        $minutes = max(1, (int) config('observability.usage_bucket_minutes', 5));
        $bucket = now()->copy()->startOfMinute();
        $bucket->subMinutes($bucket->minute % $minutes);
        $stamp = $bucket->format('YmdHi');

        Cache::put('obs:usage:registry:'.$stamp, ['1:0' => '1:0'], now()->addHour());
        Cache::put(sprintf('obs:usage:%s:1:0:requests', $stamp), 7, now()->addHour());
        Cache::put(sprintf('obs:usage:%s:1:0:users', $stamp), ['10' => true, '11' => true], now()->addHour());
        Cache::put(sprintf('obs:usage:%s:1:0:sessions', $stamp), ['abc' => true], now()->addHour());

        Artisan::call('observability:flush-usage');

        $row = UsageRollup::withoutTenancy()
            ->where('church_id', 1)
            ->whereNull('service_id')
            ->where('bucket_start', $bucket)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(7, $row->request_count);
        $this->assertSame(2, $row->active_users);
        $this->assertSame(1, $row->unique_sessions);
    }
}

<?php

namespace Tests\Feature\Observability;

use App\Models\InfraSample;
use App\Observability\Adapters\LocalProcFsAdapter;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

class InfraObservabilityTest extends EventModuleTestCase
{
    public function test_local_proc_adapter_returns_host_sample(): void
    {
        $sample = (new LocalProcFsAdapter())->sample();
        $this->assertIsArray($sample);
        $this->assertArrayHasKey('host', $sample);
        $this->assertSame('local_proc', $sample['source']);
    }

    public function test_sample_infra_command_persists_row_with_local_proc(): void
    {
        config(['observability.enabled' => true, 'observability.infra_adapter' => 'local_proc']);
        $this->app->forgetInstance(\App\Observability\Contracts\InfraMetricsAdapter::class);
        $this->app->singleton(
            \App\Observability\Contracts\InfraMetricsAdapter::class,
            fn () => new LocalProcFsAdapter()
        );

        Artisan::call('observability:sample-infra');

        $this->assertTrue(InfraSample::query()->exists());
    }

    public function test_load_tab_shows_infra_samples_for_superadmin(): void
    {
        InfraSample::query()->create([
            'sampled_at' => now(),
            'host' => 'test-host',
            'load_1' => 0.5,
            'load_5' => 0.4,
            'mem_used_mb' => 100,
            'mem_total_mb' => 200,
            'disk_used_pct' => 55.5,
            'source' => 'local_proc',
        ]);

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'infra-super@example.com',
            'registration_completed' => true,
            'is_verified' => true,
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.observability.index', ['tab' => 'load']))
            ->assertOk()
            ->assertSee('test-host', false)
            ->assertSee('55.5', false);
    }
}

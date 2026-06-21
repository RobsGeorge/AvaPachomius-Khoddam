<?php

namespace Tests\Feature\Events;

use App\Models\EventModuleTestRun;
use App\Services\EventModuleTestRunner;
use Tests\Support\EventModuleTestCase;

class SuperAdminEventTestsDashboardTest extends EventModuleTestCase
{
    public function test_superadmin_sees_test_results_dashboard(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'tests@example.com']);

        EventModuleTestRun::create([
            'suite' => 'unit',
            'passed' => 5,
            'failed' => 0,
            'total' => 5,
            'duration_ms' => 120,
            'summary' => 'All 5 tests passed',
            'output' => 'OK',
            'status' => 'passed',
            'triggered_by_id' => $super->user_id,
            'created_at' => now(),
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.events.tests.index'))
            ->assertOk()
            ->assertSee('unit')
            ->assertSee('All 5 tests passed');
    }

    public function test_non_superadmin_cannot_access_tests_dashboard(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('superadmin.events.tests.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_trigger_test_run(): void
    {
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'run-tests@example.com']);

        $this->mock(EventModuleTestRunner::class, function ($mock) use ($super): void {
            $mock->shouldReceive('runSuite')
                ->once()
                ->with('unit', \Mockery::on(fn ($user) => $user->user_id === $super->user_id))
                ->andReturn(EventModuleTestRun::create([
                    'suite' => 'unit',
                    'passed' => 3,
                    'failed' => 0,
                    'total' => 3,
                    'duration_ms' => 50,
                    'summary' => 'All 3 tests passed',
                    'output' => 'OK',
                    'status' => 'passed',
                    'triggered_by_id' => $super->user_id,
                    'created_at' => now(),
                ]));
        });

        $this->actingAs($super)
            ->post(route('superadmin.events.tests.run'), ['suite' => 'unit'])
            ->assertRedirect(route('superadmin.events.tests.index'))
            ->assertSessionHas('success');
    }
}

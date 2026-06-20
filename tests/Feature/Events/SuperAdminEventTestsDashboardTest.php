<?php

namespace Tests\Feature\Events;

use App\Models\EventModuleTestRun;
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
}

<?php

namespace Tests\Feature;

use App\Services\ServerLogReader;
use Tests\Support\EventModuleTestCase;

class SuperAdminServerLogDashboardTest extends EventModuleTestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logsDir = storage_path('framework/testing/server-logs-'.uniqid('', true));
        mkdir($this->logsDir, 0777, true);

        file_put_contents(
            $this->logsDir.'/laravel.log',
            "[2026-08-02 08:15:30] testing.ERROR: Dashboard probe failed\n".
            "[2026-08-02 08:16:00] testing.INFO: Ignored noise\n"
        );

        $this->app->instance(ServerLogReader::class, new ServerLogReader($this->logsDir));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logsDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->logsDir);
        parent::tearDown();
    }

    public function test_superadmin_can_view_server_log_errors(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'server-logs@example.com',
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.server-logs.index'))
            ->assertOk()
            ->assertSee(__('pages.server_logs_title'), false)
            ->assertSee('2026-08-02 08:15:30', false)
            ->assertSee('Dashboard probe failed', false)
            ->assertDontSee('Ignored noise', false);
    }

    public function test_superadmin_can_view_all_log_levels(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'server-logs-all@example.com',
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.server-logs.index', ['level' => 'all']))
            ->assertOk()
            ->assertSee('Ignored noise', false)
            ->assertSee('Dashboard probe failed', false);
    }

    public function test_non_superadmin_cannot_access_server_logs(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('superadmin.server-logs.index'))
            ->assertForbidden();
    }

    public function test_server_logs_link_appears_on_superadmin_hub(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'server-logs-hub@example.com',
            'registration_completed' => true,
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(route('superadmin.server-logs.index', [], false), false)
            ->assertSee(__('nav.server_logs'), false);
    }
}

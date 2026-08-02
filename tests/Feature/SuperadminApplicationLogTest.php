<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\Support\EventModuleTestCase;

class SuperadminApplicationLogTest extends EventModuleTestCase
{
    private ?string $logPath = null;

    protected function tearDown(): void
    {
        if ($this->logPath !== null && File::exists($this->logPath)) {
            File::delete($this->logPath);
        }

        parent::tearDown();
    }

    private function superadmin(): User
    {
        return $this->createUser([
            'is_superadmin' => true,
            'email' => 'app-logs-super@example.com',
            'registration_completed' => true,
        ]);
    }

    private function seedLogFile(string $contents): void
    {
        $dir = storage_path('logs');
        File::ensureDirectoryExists($dir);
        $this->logPath = $dir.'/laravel.log';
        File::put($this->logPath, $contents);
    }

    public function test_superadmin_can_view_application_log_entries(): void
    {
        $this->seedLogFile(
            "[2026-08-02 10:15:30] testing.ERROR: Payment webhook failed {\"order_id\":42}\n".
            "[2026-08-02 10:16:01] testing.INFO: Scheduler heartbeat recorded\n"
        );

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'laravel.log']))
            ->assertOk()
            ->assertSee(__('pages.application_logs_title'), false)
            ->assertSee('2026-08-02 10:15:30', false)
            ->assertSee('Payment webhook failed', false)
            ->assertSee('ERROR', false);
    }

    public function test_non_superadmin_cannot_view_application_logs(): void
    {
        $this->seedLogFile("[2026-08-02 10:15:30] testing.ERROR: Hidden failure\n");

        $this->actingAs($this->createUser(['email' => 'app-logs-plain@example.com']))
            ->get(route('superadmin.logs.index'))
            ->assertForbidden();
    }

    public function test_invalid_log_file_parameter_is_ignored(): void
    {
        $this->seedLogFile("[2026-08-02 10:15:30] testing.ERROR: Safe entry\n");

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => '../../../.env']))
            ->assertOk()
            ->assertSee('Safe entry', false)
            ->assertDontSee('.env', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Support\EventModuleTestCase;

class SuperAdminLogsTest extends EventModuleTestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('logs/test-server-logs-'.uniqid().'.log');
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    private function writeLog(string $contents): void
    {
        file_put_contents($this->logPath, $contents);
    }

    private function logFilename(): string
    {
        return basename($this->logPath);
    }

    private function superadmin(array $overrides = []): User
    {
        return $this->createUser(array_merge(['is_superadmin' => true], $overrides));
    }

    public function test_superadmin_can_view_the_logs_dashboard_with_time_and_message(): void
    {
        $super = $this->superadmin(['email' => 'logs-super@example.com']);

        $this->writeLog(
            "[2026-01-01 10:00:00] local.ERROR: Something exploded {\"exception\":\"[object] (Exception(code: 0): boom)\"}\n".
            "[2026-01-01 10:05:00] local.INFO: Everything fine\n"
        );

        $this->actingAs($super)
            ->get(route('superadmin.logs.index', ['file' => $this->logFilename()]))
            ->assertOk()
            ->assertSee(__('pages.server_logs_title'))
            ->assertSee('2026-01-01 10:00:00')
            ->assertSee('Something exploded', false)
            ->assertSee('ERROR', false)
            ->assertSee('2026-01-01 10:05:00')
            ->assertSee('Everything fine', false);
    }

    public function test_stack_trace_lines_stay_attached_to_their_entry_without_being_parsed(): void
    {
        $super = $this->superadmin(['email' => 'logs-stack@example.com']);

        $this->writeLog(
            "[2026-02-02 08:00:00] local.ERROR: Boom happened\n".
            "#0 /app/Foo.php(10): bar()\n".
            "#1 {main}\n"
        );

        $this->actingAs($super)
            ->get(route('superadmin.logs.index', ['file' => $this->logFilename()]))
            ->assertOk()
            ->assertSee('Boom happened', false)
            ->assertSee('#0 /app/Foo.php(10): bar()', false);
    }

    public function test_level_filter_narrows_results(): void
    {
        $super = $this->superadmin(['email' => 'logs-filter@example.com']);

        $this->writeLog(
            "[2026-03-01 09:00:00] local.ERROR: An error occurred\n".
            "[2026-03-01 09:05:00] local.INFO: Just info\n"
        );

        $this->actingAs($super)
            ->get(route('superadmin.logs.index', ['file' => $this->logFilename(), 'level' => 'ERROR']))
            ->assertOk()
            ->assertSee('An error occurred', false)
            ->assertDontSee('Just info', false);
    }

    public function test_search_filters_by_message_text(): void
    {
        $super = $this->superadmin(['email' => 'logs-search@example.com']);

        $this->writeLog(
            "[2026-04-01 09:00:00] local.ERROR: Payment gateway timeout\n".
            "[2026-04-01 09:05:00] local.ERROR: Unrelated failure\n"
        );

        $this->actingAs($super)
            ->get(route('superadmin.logs.index', ['file' => $this->logFilename(), 'q' => 'payment']))
            ->assertOk()
            ->assertSee('Payment gateway timeout', false)
            ->assertDontSee('Unrelated failure', false);
    }

    public function test_non_superadmin_cannot_access_logs_dashboard(): void
    {
        $user = $this->createUser(['email' => 'logs-plain@example.com']);

        $this->actingAs($user)
            ->get(route('superadmin.logs.index'))
            ->assertForbidden();
    }

    public function test_dashboard_shows_empty_state_when_no_log_files_exist(): void
    {
        $directory = storage_path('logs');
        $backupDir = storage_path('logs-test-backup-'.uniqid());
        mkdir($backupDir, 0777, true);

        $existing = glob($directory.'/*.log') ?: [];
        foreach ($existing as $path) {
            rename($path, $backupDir.'/'.basename($path));
        }

        try {
            $super = $this->superadmin(['email' => 'logs-none@example.com']);

            $this->actingAs($super)
                ->get(route('superadmin.logs.index'))
                ->assertOk()
                ->assertSee(__('pages.server_logs_no_files'));
        } finally {
            foreach (glob($backupDir.'/*.log') ?: [] as $path) {
                rename($path, $directory.'/'.basename($path));
            }
            rmdir($backupDir);
        }
    }
}

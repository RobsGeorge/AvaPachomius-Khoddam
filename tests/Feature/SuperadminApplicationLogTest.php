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

    public function test_plain_scheduler_cron_lines_are_shown(): void
    {
        $dir = storage_path('logs');
        File::ensureDirectoryExists($dir);
        $this->logPath = $dir.'/scheduler-cron.log';
        File::put($this->logPath, "Running scheduled command: inspire\nCompleted successfully.\n");

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'scheduler-cron.log']))
            ->assertOk()
            ->assertSee('Running scheduled command: inspire', false)
            ->assertSee('Completed successfully.', false);
    }

    public function test_search_filters_entries_by_message_text(): void
    {
        $this->seedLogFile(
            "[2026-08-02 10:00:00] testing.ERROR: Payment gateway timeout\n".
            "[2026-08-02 10:01:00] testing.ERROR: Unrelated failure\n"
        );

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'laravel.log', 'q' => 'payment']))
            ->assertOk()
            ->assertSee('Payment gateway timeout', false)
            ->assertDontSee('Unrelated failure', false);
    }

    public function test_pagination_pages_back_through_older_entries(): void
    {
        // The page size is clamped to a minimum of 10, so use enough entries to span
        // two pages of the smallest allowed page size.
        $lines = '';
        for ($i = 1; $i <= 12; $i++) {
            $lines .= sprintf("[2026-08-02 09:%02d:00] testing.ERROR: Entry number %d\n", $i, $i);
        }
        $this->seedLogFile($lines);

        // Page 1 (newest first) shows the ten most recent entries. ("Entry number 2" is
        // used for the absence check, not "1", since "Entry number 1" is a substring
        // of "Entry number 12"/"11"/"10" which legitimately are on this page.)
        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'laravel.log', 'lines' => 10]))
            ->assertOk()
            ->assertSee('Entry number 12', false)
            ->assertSee('Entry number 3', false)
            ->assertDontSee('Entry number 2', false);

        // Page 2 pages back to the two oldest, remaining entries.
        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'laravel.log', 'lines' => 10, 'page' => 2]))
            ->assertOk()
            ->assertSee('Entry number 1', false)
            ->assertSee('Entry number 2', false)
            ->assertDontSee('Entry number 12', false);
    }

    public function test_out_of_range_page_clamps_to_the_last_page_instead_of_showing_empty(): void
    {
        $this->seedLogFile("[2026-08-02 09:00:00] testing.ERROR: Only entry\n");

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'laravel.log', 'page' => 999]))
            ->assertOk()
            ->assertSee('Only entry', false)
            ->assertDontSee(__('pages.application_logs_empty'), false);
    }

    public function test_discovers_a_custom_log_file_beyond_the_fixed_whitelist(): void
    {
        $dir = storage_path('logs');
        File::ensureDirectoryExists($dir);
        $this->logPath = $dir.'/custom-worker.log';
        File::put($this->logPath, "[2026-08-02 10:00:00] testing.INFO: Custom worker tick\n");

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'custom-worker.log']))
            ->assertOk()
            ->assertSee('custom-worker.log', false)
            ->assertSee('Custom worker tick', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ServerLogReader;
use Illuminate\Support\Facades\File;
use Tests\Support\EventModuleTestCase;

/**
 * Superadmin server-log viewer: reads storage/logs/*.log and renders the message
 * plus its timestamp. The log directory is redirected to a scratch folder so the
 * suite never touches (or depends on) the real application log.
 */
class SuperAdminServerLogsTest extends EventModuleTestCase
{
    private string $logDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDirectory = storage_path('framework/testing/server-logs-'.uniqid());
        File::ensureDirectoryExists($this->logDirectory);
        // Point the logger at a name no fixture uses, so a stray Log:: call during a
        // request cannot alter the file under assertion.
        config(['logging.channels.single.path' => $this->logDirectory.'/app-under-test.log']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->logDirectory);

        parent::tearDown();
    }

    private function superadmin(): User
    {
        return $this->createUser([
            'is_superadmin' => true,
            'email' => 'server-logs-super@example.com',
        ]);
    }

    private function writeLog(string $name, string $contents): void
    {
        File::put($this->logDirectory.'/'.$name, $contents);
    }

    private function sampleLog(): string
    {
        return <<<'LOG'
        [2026-08-01 10:15:00] production.INFO: Scheduler heartbeat recorded
        [2026-08-01 11:20:31] production.ERROR: Undefined church for tenant resolution {"exception":"[object] (RuntimeException(code: 0))"}
        #0 /var/www/app/Tenancy/TenantContext.php(52): resolve()
        #1 {main}
        [2026-08-01 12:00:05] production.WARNING: Payment gateway retry scheduled

        LOG;
    }

    public function test_superadmin_sees_log_messages_with_their_time(): void
    {
        $this->writeLog('laravel.log', $this->sampleLog());

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index'))
            ->assertOk()
            ->assertSee(__('server_logs.title'))
            ->assertSee('laravel.log')
            ->assertSee('Undefined church for tenant resolution')
            ->assertSee('2026-08-01 11:20:31')
            ->assertSee('Scheduler heartbeat recorded')
            ->assertSee('ERROR');
    }

    public function test_entries_are_ordered_newest_first(): void
    {
        $this->writeLog('laravel.log', $this->sampleLog());

        $body = $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index'))
            ->assertOk()
            ->getContent();

        $newest = strpos($body, 'Payment gateway retry scheduled');
        $oldest = strpos($body, 'Scheduler heartbeat recorded');

        $this->assertNotFalse($newest);
        $this->assertNotFalse($oldest);
        $this->assertLessThan($oldest, $newest, 'The most recent entry should render before older ones.');
    }

    public function test_level_and_search_filters_narrow_the_entries(): void
    {
        $this->writeLog('laravel.log', $this->sampleLog());
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->get(route('superadmin.logs.index', ['level' => 'error']))
            ->assertOk()
            ->assertSee('Undefined church for tenant resolution')
            ->assertDontSee('Scheduler heartbeat recorded');

        $this->actingAs($admin)
            ->get(route('superadmin.logs.index', ['q' => 'payment gateway']))
            ->assertOk()
            ->assertSee('Payment gateway retry scheduled')
            ->assertDontSee('Undefined church for tenant resolution');

        $this->actingAs($admin)
            ->get(route('superadmin.logs.index', ['q' => 'nothing matches this']))
            ->assertOk()
            ->assertSee(__('server_logs.no_entries'));
    }

    public function test_stack_trace_lines_are_kept_with_their_entry(): void
    {
        $this->writeLog('laravel.log', $this->sampleLog());

        $result = app(ServerLogReader::class)->read('laravel.log', ['level' => 'error']);

        $this->assertCount(1, $result['entries']);
        $entry = $result['entries'][0];

        $this->assertSame('error', $entry['level']);
        $this->assertSame('2026-08-01 11:20:31', $entry['time']->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('Undefined church for tenant resolution', $entry['message']);
        $this->assertStringContainsString('TenantContext.php', $entry['detail']);
    }

    public function test_a_specific_file_can_be_selected_and_unknown_files_are_rejected(): void
    {
        $this->writeLog('laravel.log', $this->sampleLog());
        $this->writeLog('scheduler-cron.log', "[2026-08-01 09:00:00] production.NOTICE: Cron tick\n");

        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->get(route('superadmin.logs.index', ['file' => 'scheduler-cron.log']))
            ->assertOk()
            ->assertSee('Cron tick')
            ->assertDontSee('Undefined church for tenant resolution');

        // Path traversal / unknown names fall back to the newest readable file.
        $this->actingAs($admin)
            ->get(route('superadmin.logs.index', ['file' => '../../.env']))
            ->assertOk();

        $this->assertFalse(app(ServerLogReader::class)->isReadableFile('../../.env'));
        $this->assertSame([], app(ServerLogReader::class)->read('../../.env')['entries']);
    }

    public function test_a_file_without_laravel_formatted_lines_is_still_shown_when_larger_than_the_tail_cap(): void
    {
        // scheduler-cron.log is plain artisan output and grows past the tail cap in a
        // few days of cron ticks; every surviving line must still be listed.
        $line = str_pad('Running [scheduler:heartbeat] ... DONE', 120, '.').PHP_EOL;
        $this->writeLog('scheduler-cron.log', str_repeat($line, (int) ceil(ServerLogReader::TAIL_BYTES / 120) + 50));

        $result = app(ServerLogReader::class)->read('scheduler-cron.log');

        $this->assertTrue($result['truncated']);
        $this->assertCount(ServerLogReader::MAX_ENTRIES, $result['entries']);

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'scheduler-cron.log']))
            ->assertOk()
            ->assertSee('Running [scheduler:heartbeat]')
            ->assertDontSee(__('server_logs.no_entries'));
    }

    public function test_only_the_half_cut_first_line_is_dropped_from_a_truncated_tail(): void
    {
        // 64 bytes per line, so the 512 KB window starts mid-line and every whole
        // line after it must survive.
        $total = (int) (ServerLogReader::TAIL_BYTES / 64) + 30;
        $lines = '';
        for ($i = 1; $i <= $total; $i++) {
            $lines .= str_pad(sprintf('cron line %06d', $i), 63, ' ').PHP_EOL;
        }
        $this->writeLog('scheduler-cron.log', $lines);

        $entries = app(ServerLogReader::class)->read('scheduler-cron.log')['entries'];

        // Newest first, and the newest whole line is the last one written.
        $this->assertStringContainsString(sprintf('cron line %06d', $total), $entries[0]['message']);

        $expectedOldest = $total - ServerLogReader::MAX_ENTRIES + 1;
        $this->assertStringContainsString(
            sprintf('cron line %06d', $expectedOldest),
            $entries[array_key_last($entries)]['message']
        );
    }

    public function test_a_symlink_out_of_the_log_directory_is_not_readable(): void
    {
        $secret = storage_path('framework/testing/server-logs-secret-'.uniqid().'.txt');
        File::put($secret, 'APP_KEY=base64:SUPERSECRET');
        symlink($secret, $this->logDirectory.'/escape.log');

        try {
            $reader = app(ServerLogReader::class);

            $this->assertSame([], array_column($reader->availableFiles(), 'name'));
            $this->assertFalse($reader->isReadableFile('escape.log'));
            $this->assertSame([], $reader->read('escape.log')['entries']);

            $this->actingAs($this->superadmin())
                ->get(route('superadmin.logs.index', ['file' => 'escape.log']))
                ->assertOk()
                ->assertDontSee('SUPERSECRET');
        } finally {
            File::delete($secret);
        }
    }

    public function test_php_error_log_timestamps_are_parsed_and_trace_lines_attach_to_the_entry(): void
    {
        $this->writeLog('php-fpm.log', <<<'LOG'
        [02-Aug-2026 09:14:02 UTC] PHP Fatal error:  Uncaught Error: Call to undefined method Church::slugg() in /var/www/app.php:31
        Stack trace:
        #0 /var/www/index.php(9): handle()
        #1 {main}
          thrown in /var/www/app.php on line 31

        LOG);

        $result = app(ServerLogReader::class)->read('php-fpm.log');

        $this->assertCount(1, $result['entries']);
        $entry = $result['entries'][0];

        $this->assertSame('2026-08-02 09:14:02', $entry['time']->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('Call to undefined method', $entry['message']);
        $this->assertStringContainsString('#0 /var/www/index.php(9)', $entry['detail']);
    }

    public function test_a_level_carried_over_from_another_file_stays_selectable(): void
    {
        $this->writeLog('laravel.log', $this->sampleLog());
        $this->writeLog('quiet.log', "[2026-08-01 08:00:00] production.INFO: Nothing to report\n");

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index', ['file' => 'quiet.log', 'level' => 'error']))
            ->assertOk()
            ->assertSee('value="error" selected', false)
            ->assertSee(__('server_logs.no_entries'));
    }

    public function test_page_renders_when_no_log_files_exist(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('superadmin.logs.index'))
            ->assertOk()
            ->assertSee(__('server_logs.no_files'));
    }

    public function test_non_superadmin_cannot_read_server_logs(): void
    {
        $this->writeLog('laravel.log', $this->sampleLog());

        $this->actingAs($this->createUser())
            ->get(route('superadmin.logs.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('superadmin.logs.index'))->assertRedirect(route('login'));
    }
}

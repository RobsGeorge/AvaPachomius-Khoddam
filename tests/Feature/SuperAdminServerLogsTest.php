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
        config(['logging.channels.single.path' => $this->logDirectory.'/laravel.log']);
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

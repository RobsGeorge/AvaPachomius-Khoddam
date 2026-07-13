<?php

namespace App\Services;

use App\Models\SystemTestRun;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the categorized automated test pipelines and records each result so the
 * SuperAdmin dashboard can render an in-portal testing report.
 *
 * Generalizes the Events-module test runner to the whole system. Every suite maps
 * to a named <testsuite> in phpunit.xml and can be run in isolation or in sequence.
 *
 * SAFETY: the phpunit subprocess is forced onto an in-memory sqlite database and
 * with Pulse/Telescope disabled, so running the suite from production php-fpm can
 * never migrate/refresh or otherwise touch the live MySQL database.
 */
class SystemTestRunner
{
    /**
     * Ordered pipeline catalog. Key => [testsuite name, translation slug].
     * Order is the sequence used by runAll() and by the deploy gate.
     *
     * @var array<string, array{suite: string, label: string}>
     */
    public const SUITES = [
        'unit'          => ['suite' => 'Unit',          'label' => 'unit'],
        'feature'       => ['suite' => 'Feature',       'label' => 'feature'],
        'smoke'         => ['suite' => 'Smoke',         'label' => 'smoke'],
        'api'           => ['suite' => 'Api',           'label' => 'api'],
        'notifications' => ['suite' => 'Notifications', 'label' => 'notifications'],
        'mail'          => ['suite' => 'Mail',          'label' => 'mail'],
        'tenancy'       => ['suite' => 'Tenancy',       'label' => 'tenancy'],
        'load'          => ['suite' => 'Load',          'label' => 'load'],
    ];

    /** @return list<string> */
    public static function suiteKeys(): array
    {
        return array_keys(self::SUITES);
    }

    /**
     * Run every pipeline in sequence, tagged with one batch id.
     *
     * @return list<SystemTestRun>
     */
    public function runAll(?User $triggeredBy = null): array
    {
        $batchId = (string) Str::uuid();
        $runs = [];

        foreach (self::suiteKeys() as $key) {
            $runs[] = $this->runSuite($key, $triggeredBy, $batchId);
        }

        return $runs;
    }

    public function runSuite(string $key, ?User $triggeredBy = null, ?string $batchId = null): SystemTestRun
    {
        $key = array_key_exists($key, self::SUITES) ? $key : 'unit';
        $suiteName = self::SUITES[$key]['suite'];
        $started = microtime(true);

        if ($reason = $this->unavailableReason()) {
            return $this->record($key, $triggeredBy, $batchId, [
                'passed' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0,
                'duration_ms' => 0, 'summary' => $reason, 'output' => $reason,
                'status' => 'failed',
            ]);
        }

        try {
            $process = Process::path(base_path())
                ->env($this->safeTestEnv())
                ->timeout(300)
                ->run([
                    $this->phpBinary(),
                    'artisan',
                    'test',
                    '--testsuite='.$suiteName,
                    '--compact',
                ]);

            $output = trim($process->output()."\n".$process->errorOutput());
            $duration = (int) round((microtime(true) - $started) * 1000);
            [$passed, $failed, $skipped] = $this->parse($output);
            $total = $passed + $failed + $skipped;
            $exit = $process->exitCode() ?? 1;

            if ($output === '') {
                $output = __('systemtests.empty_output');
            }

            return $this->record($key, $triggeredBy, $batchId, [
                'passed' => $passed,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => $total,
                'duration_ms' => $duration,
                'summary' => $this->summarize($passed, $failed, $skipped, $total),
                'output' => $output,
                'status' => $exit === 0 && $failed === 0 ? 'passed' : 'failed',
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->record($key, $triggeredBy, $batchId, [
                'passed' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'summary' => __('systemtests.run_failed', ['message' => $e->getMessage()]),
                'output' => (string) $e,
                'status' => 'failed',
            ]);
        }
    }

    public function unavailableReason(): ?string
    {
        if (! Schema::hasTable('system_test_runs')) {
            return __('systemtests.table_missing');
        }

        if (! file_exists(base_path('vendor/bin/phpunit'))
            && ! class_exists(\PHPUnit\TextUI\Application::class)) {
            return __('systemtests.phpunit_missing');
        }

        if (! class_exists(\Tests\TestCase::class)) {
            return __('systemtests.autoload_missing');
        }

        if (! extension_loaded('pdo_sqlite')) {
            return __('systemtests.sqlite_missing');
        }

        return null;
    }

    /**
     * Environment forced onto the phpunit subprocess. Mirrors phpunit.xml so the
     * run is isolated from production services and data.
     *
     * @return array<string, string>
     */
    private function safeTestEnv(): array
    {
        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
        ];
    }

    private function phpBinary(): string
    {
        // Allow overriding when php-fpm's PHP_BINARY is not a usable CLI binary.
        return env('SYSTEM_TEST_PHP_BINARY') ?: PHP_BINARY;
    }

    private function summarize(int $passed, int $failed, int $skipped, int $total): string
    {
        if ($total === 0) {
            return __('systemtests.no_tests');
        }

        if ($failed === 0) {
            return __('systemtests.all_passed', ['total' => $total])
                .($skipped > 0 ? ' · '.__('systemtests.skipped_n', ['n' => $skipped]) : '');
        }

        return __('systemtests.failed_summary', ['failed' => $failed, 'passed' => $passed]);
    }

    /**
     * Parse Pest/PHPUnit `--compact` summary line, e.g.
     * "Tests:  2 failed, 1 skipped, 40 passed (123 assertions)".
     *
     * @return array{0:int,1:int,2:int} [passed, failed, skipped]
     */
    private function parse(string $output): array
    {
        $passed = $failed = $skipped = 0;

        if (preg_match('/(\d+)\s+passed/', $output, $m)) {
            $passed = (int) $m[1];
        }
        if (preg_match('/(\d+)\s+failed/', $output, $m)) {
            $failed = (int) $m[1];
        }
        if (preg_match('/(\d+)\s+skipped/', $output, $m)) {
            $skipped = (int) $m[1];
        }
        if (preg_match('/(\d+)\s+risked/', $output, $m)) {
            $skipped += (int) $m[1];
        }

        return [$passed, $failed, $skipped];
    }

    /** @param array<string, mixed> $result */
    private function record(string $suite, ?User $triggeredBy, ?string $batchId, array $result): SystemTestRun
    {
        return SystemTestRun::create([
            'suite' => $suite,
            'passed' => $result['passed'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'] ?? 0,
            'total' => $result['total'],
            'duration_ms' => $result['duration_ms'],
            'batch_id' => $batchId,
            'summary' => $result['summary'],
            'output' => $result['output'],
            'status' => $result['status'],
            'triggered_by_id' => $triggeredBy?->user_id,
            'created_at' => now(),
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\EventModuleTestRun;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EventModuleTestRunner
{
    /** @return list<EventModuleTestRun> */
    public function runAll(?User $triggeredBy = null): array
    {
        $runs = [];

        foreach (['unit', 'feature', 'load'] as $suite) {
            $runs[] = $this->runSuite($suite, $triggeredBy);
        }

        return $runs;
    }

    public function runSuite(string $suite, ?User $triggeredBy = null): EventModuleTestRun
    {
        $path = $this->suitePath($suite);
        $started = microtime(true);

        if ($reason = $this->unavailableReason($path)) {
            return $this->recordRun($suite, $triggeredBy, [
                'passed' => 0,
                'failed' => 0,
                'total' => 0,
                'duration_ms' => 0,
                'summary' => $reason,
                'output' => $reason,
                'status' => 'failed',
            ]);
        }

        try {
            $process = Process::path(base_path())
                ->timeout(300)
                ->run([
                    PHP_BINARY,
                    'artisan',
                    'test',
                    '--compact',
                    $path,
                ]);

            $output = trim($process->output()."\n".$process->errorOutput());
            $duration = (int) round((microtime(true) - $started) * 1000);
            [$passed, $failed, $total] = $this->parsePHPUnitOutput($output);
            $exitCode = $process->exitCode() ?? 1;

            if ($output === '') {
                $output = __('events.tests_empty_output');
            }

            return $this->recordRun($suite, $triggeredBy, [
                'passed' => $passed,
                'failed' => $failed,
                'total' => $total,
                'duration_ms' => $duration,
                'summary' => $failed === 0 && $total > 0
                    ? "All {$total} tests passed"
                    : ($total === 0
                        ? __('events.tests_no_tests_executed')
                        : "{$failed} failed, {$passed} passed"),
                'output' => $output,
                'status' => $exitCode === 0 && $failed === 0 && $total > 0 ? 'passed' : 'failed',
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->recordRun($suite, $triggeredBy, [
                'passed' => 0,
                'failed' => 0,
                'total' => 0,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'summary' => __('events.tests_run_failed', ['message' => $e->getMessage()]),
                'output' => (string) $e,
                'status' => 'failed',
            ]);
        }
    }

    public function unavailableReason(string $path): ?string
    {
        if (! Schema::hasTable('event_module_test_runs')) {
            return __('events.tests_table_missing');
        }

        if (! is_dir(base_path($path))) {
            return __('events.tests_path_missing', ['path' => $path]);
        }

        if (! file_exists(base_path('vendor/bin/phpunit'))
            && ! class_exists(\PHPUnit\TextUI\Application::class)) {
            return __('events.tests_phpunit_missing');
        }

        if (! class_exists(\Tests\TestCase::class)) {
            return __('events.tests_autoload_missing');
        }

        if (! extension_loaded('pdo_sqlite')) {
            return __('events.tests_sqlite_missing');
        }

        return null;
    }

    private function suitePath(string $suite): string
    {
        return match ($suite) {
            'unit' => 'tests/Unit/Events',
            'feature' => 'tests/Feature/Events',
            'load' => 'tests/Load/Events',
            default => 'tests/Unit/Events',
        };
    }

    /** @param array{passed: int, failed: int, total: int, duration_ms: int, summary: string, output: string, status: string} $result */
    private function recordRun(string $suite, ?User $triggeredBy, array $result): EventModuleTestRun
    {
        return EventModuleTestRun::create([
            'suite' => $suite,
            'passed' => $result['passed'],
            'failed' => $result['failed'],
            'total' => $result['total'],
            'duration_ms' => $result['duration_ms'],
            'summary' => $result['summary'],
            'output' => $result['output'],
            'status' => $result['status'],
            'triggered_by_id' => $triggeredBy?->user_id,
            'created_at' => now(),
        ]);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function parsePHPUnitOutput(string $output): array
    {
        $passed = 0;
        $failed = 0;

        if (preg_match('/(\d+)\s+passed/', $output, $m)) {
            $passed = (int) $m[1];
        }
        if (preg_match('/(\d+)\s+failed/', $output, $m)) {
            $failed = (int) $m[1];
        }

        return [$passed, $failed, $passed + $failed];
    }
}

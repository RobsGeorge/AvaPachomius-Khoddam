<?php

namespace App\Services;

use App\Models\EventModuleTestRun;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class EventModuleTestRunner
{
    public function runAll(?User $triggeredBy = null): void
    {
        foreach (['unit', 'feature', 'load'] as $suite) {
            $this->runSuite($suite, $triggeredBy);
        }
    }

    public function runSuite(string $suite, ?User $triggeredBy = null): EventModuleTestRun
    {
        $started = microtime(true);

        $path = match ($suite) {
            'unit' => 'tests/Unit/Events',
            'feature' => 'tests/Feature/Events',
            'load' => 'tests/Load/Events',
            default => 'tests/Unit/Events',
        };

        $exitCode = Artisan::call('test', ['--compact' => true, 'path' => $path]);

        $output = Artisan::output();
        $duration = (int) round((microtime(true) - $started) * 1000);

        [$passed, $failed, $total] = $this->parsePHPUnitOutput($output);

        return EventModuleTestRun::create([
            'suite' => $suite,
            'passed' => $passed,
            'failed' => $failed,
            'total' => $total,
            'duration_ms' => $duration,
            'summary' => $failed === 0
                ? "All {$total} tests passed"
                : "{$failed} failed, {$passed} passed",
            'output' => $output,
            'status' => $exitCode === 0 ? 'passed' : 'failed',
            'triggered_by_id' => $triggeredBy?->user_id,
            'created_at' => now(),
        ]);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function parsePHPUnitOutput(string $output): array
    {
        $passed = 0;
        $failed = 0;

        if (preg_match('/(\d+) passed/', $output, $m)) {
            $passed = (int) $m[1];
        }
        if (preg_match('/(\d+) failed/', $output, $m)) {
            $failed = (int) $m[1];
        }

        return [$passed, $failed, $passed + $failed];
    }
}

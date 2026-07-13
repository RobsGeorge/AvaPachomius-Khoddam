<?php

namespace App\Http\Controllers;

use App\Models\SystemTestRun;
use App\Services\SystemTestRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SuperAdminSystemTestController extends Controller
{
    public function index()
    {
        $runs = SystemTestRun::with('triggeredBy')
            ->orderByDesc('test_run_id')
            ->paginate(30);

        // Latest result per suite for the status board.
        $latestBySuite = SystemTestRun::query()
            ->orderByDesc('test_run_id')
            ->get()
            ->unique('suite')
            ->keyBy('suite');

        $suites = SystemTestRunner::suiteKeys();

        return view('superadmin.system-tests.index', compact('runs', 'latestBySuite', 'suites'));
    }

    public function run(Request $request, SystemTestRunner $runner)
    {
        $suite = (string) $request->input('suite', 'all');
        $allowed = array_merge(['all'], SystemTestRunner::suiteKeys());

        if (! in_array($suite, $allowed, true)) {
            $suite = 'all';
        }

        try {
            $results = $suite === 'all'
                ? $runner->runAll(Auth::user())
                : [$runner->runSuite($suite, Auth::user())];

            $anyFailed = collect($results)->contains(
                fn (SystemTestRun $run) => ! $run->isPassing()
            );

            return redirect()
                ->route('superadmin.system-tests.index')
                ->with(
                    $anyFailed ? 'warning' : 'success',
                    $anyFailed
                        ? __('systemtests.run_completed_with_failures')
                        : __('systemtests.run_completed_ok')
                );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('superadmin.system-tests.index')
                ->with('error', __('systemtests.run_failed', ['message' => $e->getMessage()]));
        }
    }

    public function show(SystemTestRun $systemTestRun)
    {
        return view('superadmin.system-tests.show', ['run' => $systemTestRun]);
    }
}

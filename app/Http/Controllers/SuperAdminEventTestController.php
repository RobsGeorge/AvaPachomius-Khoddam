<?php

namespace App\Http\Controllers;

use App\Models\EventModuleTestRun;
use App\Services\EventModuleTestRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SuperAdminEventTestController extends Controller
{
    public function index()
    {
        $runs = EventModuleTestRun::with('triggeredBy')
            ->orderByDesc('test_run_id')
            ->paginate(20);

        $latestBySuite = EventModuleTestRun::query()
            ->orderByDesc('test_run_id')
            ->get()
            ->unique('suite');

        return view('superadmin.events.tests', compact('runs', 'latestBySuite'));
    }

    public function run(Request $request, EventModuleTestRunner $runner)
    {
        $suite = $request->input('suite', 'all');
        $allowed = ['all', 'unit', 'feature', 'load'];

        if (! in_array($suite, $allowed, true)) {
            $suite = 'all';
        }

        try {
            $results = $suite === 'all'
                ? $runner->runAll(Auth::user())
                : [$runner->runSuite($suite, Auth::user())];

            $anyFailed = collect($results)->contains(
                fn (EventModuleTestRun $run) => $run->status !== 'passed'
            );

            return redirect()
                ->route('superadmin.events.tests.index')
                ->with(
                    $anyFailed ? 'warning' : 'success',
                    $anyFailed
                        ? __('events.tests_run_completed_with_failures')
                        : __('events.tests_run_started')
                );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('superadmin.events.tests.index')
                ->with('error', __('events.tests_run_failed', ['message' => $e->getMessage()]));
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\EventModuleTestRun;
use App\Services\EventModuleTestRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if ($suite === 'all') {
            $runner->runAll(Auth::user());
        } else {
            $runner->runSuite($suite, Auth::user());
        }

        return redirect()->route('superadmin.events.tests.index')
            ->with('success', __('events.tests_run_started'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ScheduledTaskRun;
use App\Services\ScheduledTaskRegistrar;
use App\Services\ScheduledTaskRunner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SuperAdminScheduledTaskController extends Controller
{
    public function index(ScheduledTaskRegistrar $registrar)
    {
        $schedule = new Schedule;
        $registrar->register($schedule);
        $tasks = $registrar->allTasksForDisplay($schedule);

        $runs = ScheduledTaskRun::with('triggeredBy')
            ->orderByDesc('run_id')
            ->paginate(30);

        return view('superadmin.scheduled-tasks.index', compact('tasks', 'runs'));
    }

    public function run(ScheduledTaskRunner $runner, string $taskKey)
    {
        abort_unless(app(ScheduledTaskRegistrar::class)->hasTask($taskKey), 404);

        try {
            $run = $runner->runManually($taskKey, Auth::user());

            return redirect()
                ->route('superadmin.scheduled-tasks.show', $run)
                ->with(
                    $run->isSuccess() ? 'success' : 'warning',
                    $run->isSuccess()
                        ? __('scheduled_tasks.run_completed_ok')
                        : __('scheduled_tasks.run_completed_with_errors')
                );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('superadmin.scheduled-tasks.index')
                ->with('error', __('scheduled_tasks.run_failed', ['message' => $e->getMessage()]));
        }
    }

    public function updateSettings(
        Request $request,
        ScheduledTaskRegistrar $registrar,
        string $taskKey,
    ) {
        abort_unless($registrar->hasTask($taskKey), 404);

        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'cron_expression' => ['nullable', 'string', 'max:120'],
        ]);

        $registrar->updateSetting($taskKey, [
            'enabled' => $request->boolean('enabled'),
            'cron_expression' => $validated['cron_expression'] ?? null,
        ], Auth::user());

        return redirect()
            ->route('superadmin.scheduled-tasks.index')
            ->with('success', __('scheduled_tasks.settings_saved'));
    }

    public function show(ScheduledTaskRun $scheduledTaskRun)
    {
        abort_unless(app(ScheduledTaskRegistrar::class)->hasTask($scheduledTaskRun->task_key), 404);

        return view('superadmin.scheduled-tasks.show', [
            'run' => $scheduledTaskRun->load('triggeredBy'),
            'task' => app(ScheduledTaskRegistrar::class)->resolveTask($scheduledTaskRun->task_key),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ScheduledTaskRun;
use App\Services\ScheduledTaskRegistrar;
use App\Services\ScheduledTaskRunner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

        return view('superadmin.scheduled-tasks.index', [
            'tasks' => $tasks,
            'runs' => $runs,
            'availableCommands' => $registrar->availableCommands(),
        ]);
    }

    public function store(Request $request, ScheduledTaskRegistrar $registrar)
    {
        try {
            $result = $registrar->createCustomTask(
                $request->all(),
                Auth::user(),
                $request->boolean('run_after_create')
            );

            if ($result['run']) {
                return redirect()
                    ->route('superadmin.scheduled-tasks.show', $result['run'])
                    ->with('success', __('scheduled_tasks.created_and_ran'));
            }

            return redirect()
                ->route('superadmin.scheduled-tasks.index')
                ->with('success', __('scheduled_tasks.created'));
        } catch (ValidationException $e) {
            return redirect()
                ->route('superadmin.scheduled-tasks.index')
                ->withErrors($e->errors())
                ->withInput();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('superadmin.scheduled-tasks.index')
                ->with('error', __('scheduled_tasks.create_failed', ['message' => $e->getMessage()]))
                ->withInput();
        }
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

        try {
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
        } catch (ValidationException $e) {
            return redirect()
                ->route('superadmin.scheduled-tasks.index')
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    public function destroy(ScheduledTaskRegistrar $registrar, string $taskKey)
    {
        abort_unless($registrar->isCustomTask($taskKey), 404);

        $registrar->deleteCustomTask($taskKey);

        return redirect()
            ->route('superadmin.scheduled-tasks.index')
            ->with('success', __('scheduled_tasks.deleted'));
    }

    public function show(ScheduledTaskRun $scheduledTaskRun, ScheduledTaskRegistrar $registrar)
    {
        return view('superadmin.scheduled-tasks.show', [
            'run' => $scheduledTaskRun->load('triggeredBy'),
            'task' => $registrar->resolveTask($scheduledTaskRun->task_key),
            'taskName' => $registrar->taskDisplayName($scheduledTaskRun->task_key),
        ]);
    }
}

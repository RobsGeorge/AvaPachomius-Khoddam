<?php

namespace App\Http\Controllers;

use App\Models\ScheduledTaskRun;
use App\Services\ScheduledTaskRegistrar;
use App\Services\ScheduledTaskReportService;
use App\Services\ScheduledTaskRunner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class SuperAdminScheduledTaskController extends Controller
{
    public function index(
        Request $request,
        ScheduledTaskRegistrar $registrar,
        ScheduledTaskReportService $reportService,
    ) {
        $schedule = new Schedule;
        $registrar->register($schedule);
        $tasks = $registrar->allTasksForDisplay($schedule);

        $runs = ScheduledTaskRun::with('triggeredBy')
            ->orderByDesc('run_id')
            ->paginate(30);

        return view('superadmin.scheduled-tasks.index', [
            'tasks' => $tasks,
            'runs' => $runs,
            'runsWithImpact' => $reportService->runsWithImpact($runs),
            'stats' => $reportService->dashboardStats($tasks),
            'reportService' => $reportService,
            'expand' => $request->query('expand'),
            'availableCommands' => $registrar->availableCommands(),
        ]);
    }

    public function store(
        Request $request,
        ScheduledTaskRegistrar $registrar,
        ScheduledTaskReportService $reportService,
    ) {
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

            return $this->indexRedirect(
                $reportService->taskExpandKey($result['task_key']),
                ['success' => __('scheduled_tasks.created')]
            );
        } catch (ValidationException $e) {
            return $this->indexRedirect('create-task')
                ->withErrors($e->errors())
                ->withInput();
        } catch (Throwable $e) {
            report($e);

            return $this->indexRedirect('create-task')
                ->with('error', __('scheduled_tasks.create_failed', ['message' => $e->getMessage()]))
                ->withInput();
        }
    }

    public function update(
        Request $request,
        ScheduledTaskRegistrar $registrar,
        ScheduledTaskReportService $reportService,
        string $taskKey,
    ) {
        abort_unless($registrar->isCustomTask($taskKey), 404);

        try {
            $registrar->updateCustomTask($taskKey, $request->all(), Auth::user());

            return $this->indexRedirect(
                $reportService->taskExpandKey($taskKey),
                ['success' => __('scheduled_tasks.updated')]
            );
        } catch (ValidationException $e) {
            return $this->indexRedirect($reportService->taskExpandKey($taskKey))
                ->withErrors($e->errors())
                ->withInput();
        } catch (Throwable $e) {
            report($e);

            return $this->indexRedirect($reportService->taskExpandKey($taskKey))
                ->with('error', __('scheduled_tasks.update_failed', ['message' => $e->getMessage()]))
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
        ScheduledTaskReportService $reportService,
        string $taskKey,
    ) {
        abort_unless($registrar->hasTask($taskKey), 404);
        abort_if($registrar->isCustomTask($taskKey), 404);

        try {
            $registrar->updateSetting($taskKey, [
                'enabled' => $request->boolean('enabled'),
                'schedule_frequency' => $request->input('schedule_frequency'),
                'schedule_time' => $request->input('schedule_time'),
                'schedule_day' => $request->input('schedule_day'),
            ], Auth::user());

            return $this->indexRedirect(
                $reportService->taskExpandKey($taskKey),
                ['success' => __('scheduled_tasks.settings_saved')]
            );
        } catch (ValidationException $e) {
            return $this->indexRedirect($reportService->taskExpandKey($taskKey))
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

    public function show(
        ScheduledTaskRun $scheduledTaskRun,
        ScheduledTaskRegistrar $registrar,
        ScheduledTaskReportService $reportService,
    ) {
        return view('superadmin.scheduled-tasks.show', [
            'run' => $scheduledTaskRun->load('triggeredBy'),
            'task' => $registrar->resolveTask($scheduledTaskRun->task_key),
            'taskName' => $registrar->taskDisplayName($scheduledTaskRun->task_key),
            'impact' => $reportService->impactForRun($scheduledTaskRun),
            'impactSummary' => $reportService->impactSummaryForRun($scheduledTaskRun),
            'reportService' => $reportService,
        ]);
    }

    /** @param array<string, mixed> $with */
    private function indexRedirect(?string $expand = null, array $with = []): RedirectResponse
    {
        $url = route('superadmin.scheduled-tasks.index');
        if ($expand !== null && $expand !== '') {
            $url .= '?expand='.urlencode($expand);
        }

        return redirect()->to($url)->with($with);
    }
}

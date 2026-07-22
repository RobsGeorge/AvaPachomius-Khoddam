<?php

namespace App\Services;

use App\Models\ScheduledTaskRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ScheduledTaskReportService
{
    public function __construct(
        private ScheduledTaskImpactParser $impactParser,
        private ScheduledTaskRegistrar $registrar,
    ) {}

    /** @param list<array<string, mixed>> $tasks */
    public function dashboardStats(array $tasks): array
    {
        $enabled = collect($tasks)->filter(fn (array $task) => ($task['enabled'] ?? false) && ($task['when_active'] ?? true));
        $since = now()->subDay();
        $recentRuns = ScheduledTaskRun::query()->where('started_at', '>=', $since)->get();
        $successful = $recentRuns->where('status', ScheduledTaskRun::STATUS_SUCCESS)->count();
        $totalRecent = $recentRuns->count();

        return [
            'total_tasks' => count($tasks),
            'enabled_tasks' => $enabled->count(),
            'custom_tasks' => collect($tasks)->where('is_custom', true)->count(),
            'runs_last_24h' => $totalRecent,
            'success_rate' => $totalRecent > 0 ? (int) round(($successful / $totalRecent) * 100) : null,
        ];
    }

    /** @return array<string, int> */
    public function impactForRun(ScheduledTaskRun $run): array
    {
        if (is_array($run->metadata['impact'] ?? null)) {
            return $run->metadata['impact'];
        }

        return $this->impactParser->parse($run->output);
    }

    public function impactSummaryForRun(ScheduledTaskRun $run): ?string
    {
        return $this->impactParser->summarize($this->impactForRun($run));
    }

    /** @param LengthAwarePaginator<int, ScheduledTaskRun>|Collection<int, ScheduledTaskRun> $runs */
    public function runsWithImpact(LengthAwarePaginator|Collection $runs): Collection
    {
        $collection = $runs instanceof LengthAwarePaginator ? $runs->getCollection() : $runs;

        return $collection->map(function (ScheduledTaskRun $run) {
            $impact = $this->impactForRun($run);

            return [
                'run' => $run,
                'impact' => $impact,
                'impact_summary' => $this->impactParser->summarize($impact),
                'task_name' => $this->registrar->taskDisplayName($run->task_key),
            ];
        });
    }

    public function taskExpandKey(string $taskKey): string
    {
        return 'task-'.str_replace('.', '-', $taskKey);
    }
}

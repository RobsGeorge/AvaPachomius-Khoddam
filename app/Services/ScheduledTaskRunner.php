<?php

namespace App\Services;

use App\Models\ScheduledTaskRun;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ScheduledTaskRunner
{
    public function beginScheduledRun(string $taskKey): ScheduledTaskRun
    {
        return $this->beginRun($taskKey, ScheduledTaskRun::TRIGGER_SCHEDULED);
    }

    public function runManually(string $taskKey, User $user): ScheduledTaskRun
    {
        abort_unless(app(ScheduledTaskRegistrar::class)->hasTask($taskKey), 404);

        $run = $this->beginRun($taskKey, ScheduledTaskRun::TRIGGER_MANUAL, $user);

        try {
            $definition = app(ScheduledTaskRegistrar::class)->resolveTask($taskKey) ?? [];
            $exitCode = $this->executeDefinition($definition);
            $output = $this->collectOutput($definition, $exitCode);
            $this->finishRun($run, $exitCode === 0, $output, $exitCode);

            return $run->fresh();
        } catch (Throwable $e) {
            report($e);
            $this->finishRun($run, false, trim($e->getMessage()), 1);

            return $run->fresh();
        }
    }

    /**
     * Finish the latest running scheduled row for a task.
     * Looks up by task_key so hooks work across container resolutions
     * (before/onSuccess must not rely on in-memory instance state).
     */
    public function finishScheduledRun(
        string $taskKey,
        bool $success,
        ?string $output = null,
        ?int $exitCode = null,
    ): void {
        $run = ScheduledTaskRun::query()
            ->where('task_key', $taskKey)
            ->where('status', ScheduledTaskRun::STATUS_RUNNING)
            ->where('trigger', ScheduledTaskRun::TRIGGER_SCHEDULED)
            ->orderByDesc('run_id')
            ->first();

        if (! $run) {
            return;
        }

        $definition = app(ScheduledTaskRegistrar::class)->resolveTask($taskKey) ?? [];
        $resolvedExit = $exitCode ?? ($success ? 0 : 1);
        $resolvedOutput = $output;
        if ($resolvedOutput === null || $resolvedOutput === '') {
            $resolvedOutput = $this->collectOutput($definition, $resolvedExit);
        }

        $this->finishRun($run, $success, $resolvedOutput, $resolvedExit);
    }

    private function beginRun(string $taskKey, string $trigger, ?User $user = null): ScheduledTaskRun
    {
        return ScheduledTaskRun::query()->create([
            'task_key' => $taskKey,
            'status' => ScheduledTaskRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'started_at' => now(),
            'triggered_by_id' => $user?->user_id,
        ]);
    }

    private function finishRun(
        ScheduledTaskRun $run,
        bool $success,
        ?string $output,
        int $exitCode,
    ): void {
        $finishedAt = now();
        $durationMs = max(0, (int) $run->started_at?->diffInMilliseconds($finishedAt));
        $impact = app(ScheduledTaskImpactParser::class)->parse($output);
        $metadata = is_array($run->metadata) ? $run->metadata : [];
        $metadata['impact'] = $impact;

        $run->update([
            'status' => $success ? ScheduledTaskRun::STATUS_SUCCESS : ScheduledTaskRun::STATUS_FAILED,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'output' => $output !== '' ? $output : null,
            'metadata' => $metadata,
            'finished_at' => $finishedAt,
        ]);
    }

    /** @param array<string, mixed> $definition */
    private function executeDefinition(array $definition): int
    {
        return match ($definition['type'] ?? null) {
            'command' => Artisan::call(
                (string) $definition['command'],
                $definition['parameters'] ?? []
            ),
            'callback' => tap(0, function () use ($definition) {
                $callback = $definition['callback'] ?? null;
                if (is_array($callback) && is_string($callback[0] ?? null) && isset($callback[1])) {
                    app($callback[0])->{$callback[1]}();
                } elseif (is_callable($callback)) {
                    $callback();
                }
            }),
            default => 1,
        };
    }

    /** @param array<string, mixed> $definition */
    private function collectOutput(array $definition, int $exitCode): string
    {
        if (($definition['type'] ?? null) === 'command') {
            return trim(Artisan::output());
        }

        return $exitCode === 0
            ? __('scheduled_tasks.callback_completed')
            : __('scheduled_tasks.callback_failed');
    }
}

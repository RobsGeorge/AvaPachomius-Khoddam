<?php

namespace App\Services;

use App\Models\ScheduledTaskRun;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ScheduledTaskRunner
{
    private ?ScheduledTaskRun $currentRun = null;

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

    public function finishCurrentRun(bool $success): void
    {
        if (! $this->currentRun) {
            return;
        }

        $definition = app(ScheduledTaskRegistrar::class)->resolveTask($this->currentRun->task_key) ?? [];
        $output = $this->collectOutput($definition, $success ? 0 : 1);
        $this->finishRun($this->currentRun, $success, $output, $success ? 0 : 1);
    }

    private function beginRun(string $taskKey, string $trigger, ?User $user = null): ScheduledTaskRun
    {
        $this->currentRun = ScheduledTaskRun::query()->create([
            'task_key' => $taskKey,
            'status' => ScheduledTaskRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'started_at' => now(),
            'triggered_by_id' => $user?->user_id,
        ]);

        return $this->currentRun;
    }

    private function finishRun(
        ScheduledTaskRun $run,
        bool $success,
        ?string $output,
        int $exitCode,
    ): void {
        $finishedAt = now();
        $durationMs = max(0, (int) $run->started_at?->diffInMilliseconds($finishedAt));

        $run->update([
            'status' => $success ? ScheduledTaskRun::STATUS_SUCCESS : ScheduledTaskRun::STATUS_FAILED,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'output' => $output !== '' ? $output : null,
            'finished_at' => $finishedAt,
        ]);

        if ($this->currentRun?->run_id === $run->run_id) {
            $this->currentRun = null;
        }
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
                if (is_array($callback)) {
                    App::call($callback);
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

<?php

namespace App\Services;

use App\Models\ScheduledTaskRun;
use App\Models\ScheduledTaskSetting;
use App\Models\User;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class ScheduledTaskRegistrar
{
    public function register(Schedule $schedule): void
    {
        foreach ($this->taskDefinitions() as $key => $definition) {
            if ($this->schemaReady() && ! $this->isEnabled($key)) {
                continue;
            }

            if (! $this->schemaReady() && ! $this->isEnabledWithoutSettings($key)) {
                continue;
            }

            $event = $this->buildEvent($schedule, $key, $definition);
            if ($event === null) {
                continue;
            }

            $event->name($key);

            if ($whenConfig = $definition['when_config'] ?? null) {
                $event->when(fn () => (bool) config($whenConfig));
            }

            if ($this->schemaReady()) {
                $event->before(function () use ($key) {
                    app(ScheduledTaskRunner::class)->beginScheduledRun($key);
                });

                $event->onSuccess(function () {
                    app(ScheduledTaskRunner::class)->finishCurrentRun(true);
                });

                $event->onFailure(function () {
                    app(ScheduledTaskRunner::class)->finishCurrentRun(false);
                });
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function taskDefinitions(): array
    {
        return config('scheduled_tasks.tasks', []);
    }

    public function taskKeys(): array
    {
        return array_keys($this->taskDefinitions());
    }

    public function hasTask(string $key): bool
    {
        return array_key_exists($key, $this->taskDefinitions());
    }

    /** @return array<string, mixed>|null */
    public function resolveTask(string $key): ?array
    {
        $definition = $this->taskDefinitions()[$key] ?? null;
        if ($definition === null) {
            return null;
        }

        $setting = $this->settingFor($key);

        return array_merge($definition, [
            'key' => $key,
            'enabled' => $setting?->enabled ?? true,
            'cron_expression' => $setting?->cron_expression,
            'when_config' => $definition['when_config'] ?? null,
            'when_active' => $this->whenConfigActive($definition),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function allTasksForDisplay(Schedule $schedule): array
    {
        $eventsByName = collect($schedule->events())
            ->filter(fn (Event $event) => filled($event->description))
            ->keyBy('description');

        $tasks = [];

        foreach ($this->taskDefinitions() as $key => $definition) {
            $resolved = $this->resolveTask($key);
            if ($resolved === null) {
                continue;
            }

            $event = $eventsByName->get($key);
            $nextRun = null;
            $expression = $resolved['cron_expression'];

            if ($event instanceof Event) {
                $expression = $event->expression;
                if ($resolved['enabled'] && $resolved['when_active']) {
                    try {
                        $nextRun = $event->nextRunDate();
                    } catch (\Throwable) {
                        $nextRun = null;
                    }
                }
            }

            $tasks[] = array_merge($resolved, [
                'expression' => $expression,
                'next_run_at' => $nextRun,
                'last_run' => ScheduledTaskRun::query()
                    ->where('task_key', $key)
                    ->orderByDesc('run_id')
                    ->first(),
            ]);
        }

        return $tasks;
    }

    public function isEnabled(string $key): bool
    {
        return $this->isEnabledWithoutSettings($key)
            && ($this->settingFor($key)?->enabled ?? true);
    }

    private function isEnabledWithoutSettings(string $key): bool
    {
        return $this->hasTask($key);
    }

    public function updateSetting(string $key, array $input, User $user): ScheduledTaskSetting
    {
        abort_unless($this->hasTask($key), 404);

        $cron = isset($input['cron_expression']) && $input['cron_expression'] !== ''
            ? trim((string) $input['cron_expression'])
            : null;

        if ($cron !== null && ! CronExpression::isValidExpression($cron)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cron_expression' => __('scheduled_tasks.invalid_cron'),
            ]);
        }

        return ScheduledTaskSetting::query()->updateOrCreate(
            ['task_key' => $key],
            [
                'enabled' => (bool) ($input['enabled'] ?? true),
                'cron_expression' => $cron,
                'updated_by_id' => $user->user_id,
            ]
        );
    }

    private function buildEvent(Schedule $schedule, string $key, array $definition): ?Event
    {
        $setting = $this->settingFor($key);

        if (($definition['type'] ?? null) === 'command') {
            $event = $schedule->command(
                (string) $definition['command'],
                $definition['parameters'] ?? []
            );
        } elseif (($definition['type'] ?? null) === 'callback') {
            $callback = $definition['callback'];
            $event = $schedule->call(function () use ($callback) {
                if (is_array($callback)) {
                    App::call($callback);
                } elseif (is_callable($callback)) {
                    $callback();
                }
            });
        } else {
            return null;
        }

        $cron = $setting?->cron_expression;
        if ($cron) {
            $event->cron($cron);
        } else {
            $this->applyDefaultSchedule($event, $definition['schedule'] ?? []);
        }

        if ($timezone = $this->resolveTimezone($definition['schedule'] ?? [])) {
            $event->timezone($timezone);
        }

        return $event;
    }

    private function applyDefaultSchedule(Event $event, array $schedule): void
    {
        $frequency = $schedule['frequency'] ?? 'daily';

        match ($frequency) {
            'daily_at' => $event->dailyAt((string) ($schedule['time'] ?? '00:00')),
            'hourly' => $event->hourly(),
            'every_five_minutes' => $event->everyFiveMinutes(),
            'monthly_on' => $event->monthlyOn(
                (int) ($schedule['day'] ?? 1),
                (string) ($schedule['time'] ?? '00:00')
            ),
            'weekly_on' => $event->weeklyOn(
                (int) ($schedule['day'] ?? 1),
                (string) ($schedule['time'] ?? '00:00')
            ),
            default => $event->daily(),
        };
    }

    private function whenConfigActive(array $definition): bool
    {
        $whenConfig = $definition['when_config'] ?? null;
        if ($whenConfig === null) {
            return true;
        }

        return (bool) config($whenConfig);
    }

    private function resolveTimezone(array $schedule): ?string
    {
        $timezoneKey = $schedule['timezone'] ?? null;
        if (! $timezoneKey) {
            return null;
        }

        $timezone = config($timezoneKey);

        return is_string($timezone) && $timezone !== '' ? $timezone : config('app.timezone');
    }

    private function settingFor(string $key): ?ScheduledTaskSetting
    {
        if (! $this->schemaReady()) {
            return null;
        }

        return ScheduledTaskSetting::query()->where('task_key', $key)->first();
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('scheduled_task_settings')
            && Schema::hasTable('scheduled_task_runs');
    }
}

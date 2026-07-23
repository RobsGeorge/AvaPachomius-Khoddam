<?php

namespace App\Services;

use App\Models\ScheduledTaskDefinition;
use App\Models\ScheduledTaskRun;
use App\Models\ScheduledTaskSetting;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ScheduledTaskRegistrar
{
    public function __construct(
        private CronScheduleBuilder $scheduleBuilder,
    ) {}

    public function register(Schedule $schedule): void
    {
        foreach ($this->taskDefinitions() as $key => $definition) {
            if ($this->schemaReady() && ! $this->isEnabled($key)) {
                continue;
            }

            if (! $this->schemaReady() && ! $this->hasTask($key)) {
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
        $builtin = config('scheduled_tasks.tasks', []);
        $custom = $this->customDefinitionsFromDatabase();

        return $builtin + $custom;
    }

    public function taskKeys(): array
    {
        return array_keys($this->taskDefinitions());
    }

    public function hasTask(string $key): bool
    {
        return array_key_exists($key, $this->taskDefinitions());
    }

    public function isCustomTask(string $key): bool
    {
        return str_starts_with($key, 'custom.');
    }

    /** @return array<string, mixed>|null */
    public function resolveTask(string $key): ?array
    {
        $definition = $this->taskDefinitions()[$key] ?? null;
        if ($definition === null) {
            return null;
        }

        if ($definition['is_custom'] ?? false) {
            $model = $this->customDefinitionModel($key);

            return array_merge($definition, [
                'key' => $key,
                'label' => $model?->localizedLabel() ?? (string) ($definition['label'] ?? $key),
                'label_en' => $model?->label_en,
                'label_ar' => $model?->label_ar,
                'description' => $model?->localizedDescription() ?? (string) ($definition['description'] ?? ''),
                'enabled' => (bool) ($model?->enabled ?? true),
                'cron_expression' => $model?->cron_expression,
                'timezone' => $model?->timezone,
                'when_active' => true,
                'command_display' => $model?->command,
            ]);
        }

        $setting = $this->settingFor($key);

        return array_merge($definition, [
            'key' => $key,
            'enabled' => $setting?->enabled ?? true,
            'cron_expression' => $setting?->cron_expression,
            'when_config' => $definition['when_config'] ?? null,
            'when_active' => $this->whenConfigActive($definition),
            'is_custom' => false,
            'command_display' => $definition['command'] ?? null,
        ]);
    }

    public function taskDisplayName(string $key): string
    {
        $resolved = $this->resolveTask($key);
        if ($resolved === null) {
            return $key;
        }

        if ($resolved['is_custom'] ?? false) {
            return (string) ($resolved['label'] ?? $key);
        }

        return __((string) ($resolved['label'] ?? $key));
    }

    /** @param array<string, mixed> $resolved */
    public function scheduleUiForTask(array $resolved): array
    {
        if (filled($resolved['cron_expression'] ?? null)) {
            return $this->scheduleBuilder->parse((string) $resolved['cron_expression']);
        }

        return $this->scheduleBuilder->scheduleUiFromConfig($resolved['schedule'] ?? []);
    }

    /** @param array<string, mixed> $resolved */
    public function scheduleLabel(array $resolved): string
    {
        return $this->scheduleBuilder->describe($this->scheduleUiForTask($resolved));
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

            $scheduleUi = $this->scheduleUiForTask($resolved);

            $tasks[] = array_merge($resolved, [
                'expression' => $expression,
                'schedule_ui' => $scheduleUi,
                'schedule_label' => $this->scheduleBuilder->describe($scheduleUi),
                'next_run_at' => $nextRun,
                'last_run' => ScheduledTaskRun::query()
                    ->where('task_key', $key)
                    ->orderByDesc('run_id')
                    ->first(),
                'recent_runs' => ScheduledTaskRun::query()
                    ->where('task_key', $key)
                    ->orderByDesc('run_id')
                    ->limit(5)
                    ->get(),
            ]);
        }

        return $tasks;
    }

    /** @return list<string> */
    public function availableCommands(): array
    {
        $blocked = config('scheduled_tasks.blocked_commands', []);

        return collect(Artisan::all())
            ->keys()
            ->filter(fn (string $name) => ! in_array($name, $blocked, true))
            ->sort()
            ->values()
            ->all();
    }

    public function isEnabled(string $key): bool
    {
        if (! $this->hasTask($key)) {
            return false;
        }

        if ($this->isCustomTask($key)) {
            return (bool) ($this->customDefinitionModel($key)?->enabled ?? true);
        }

        return (bool) ($this->settingFor($key)?->enabled ?? true);
    }

    public function updateSetting(string $key, array $input, User $user): ScheduledTaskSetting
    {
        abort_unless($this->hasTask($key), 404);
        abort_if($this->isCustomTask($key), 404);

        $schedule = $this->scheduleBuilder->validateScheduleInput($input);
        $cron = $this->scheduleBuilder->build($schedule);

        return ScheduledTaskSetting::query()->updateOrCreate(
            ['task_key' => $key],
            [
                'enabled' => (bool) ($input['enabled'] ?? true),
                'cron_expression' => $cron,
                'updated_by_id' => $user->user_id,
            ]
        );
    }

    public function updateCustomTask(string $key, array $input, User $user): ScheduledTaskDefinition
    {
        abort_unless($this->isCustomTask($key) && $this->hasTask($key), 404);

        $definition = $this->customDefinitionModel($key);
        abort_unless($definition, 404);

        $validated = $this->validateCustomTaskInput($input, $key);
        $schedule = $this->scheduleBuilder->validateScheduleInput($input);
        $cron = $this->scheduleBuilder->build($schedule);

        $definition->update([
            'label_en' => $validated['label_en'],
            'label_ar' => $validated['label_ar'],
            'description_en' => $validated['description_en'] ?? null,
            'description_ar' => $validated['description_ar'] ?? null,
            'command' => $validated['command'],
            'parameters' => $validated['parameters'] ?? [],
            'cron_expression' => $cron,
            'timezone' => $validated['timezone'] ?? null,
            'enabled' => (bool) ($input['enabled'] ?? true),
            'updated_by_id' => $user->user_id,
        ]);

        return $definition->fresh();
    }

    public function createCustomTask(array $input, User $user, bool $runAfterCreate = false): array
    {
        $this->assertDefinitionsSchemaReady();

        $validated = $this->validateCustomTaskInput($input);
        $schedule = $this->scheduleBuilder->validateScheduleInput($input);
        $cron = $this->scheduleBuilder->build($schedule);

        $taskKey = $this->uniqueCustomTaskKey($validated['slug']);

        $definition = ScheduledTaskDefinition::query()->create([
            'task_key' => $taskKey,
            'label_en' => $validated['label_en'],
            'label_ar' => $validated['label_ar'],
            'description_en' => $validated['description_en'] ?? null,
            'description_ar' => $validated['description_ar'] ?? null,
            'command' => $validated['command'],
            'parameters' => $validated['parameters'] ?? [],
            'cron_expression' => $cron,
            'timezone' => $validated['timezone'] ?? null,
            'enabled' => (bool) ($input['enabled'] ?? true),
            'created_by_id' => $user->user_id,
            'updated_by_id' => $user->user_id,
        ]);

        $run = null;
        if ($runAfterCreate) {
            $run = app(ScheduledTaskRunner::class)->runManually($taskKey, $user);
        }

        return ['definition' => $definition, 'run' => $run, 'task_key' => $taskKey];
    }

    public function deleteCustomTask(string $key): void
    {
        abort_unless($this->isCustomTask($key) && $this->hasTask($key), 404);

        ScheduledTaskDefinition::query()->where('task_key', $key)->delete();
    }

    /** @param array<string, mixed> $input */
    private function validateCustomTaskInput(array $input, ?string $existingKey = null): array
    {
        $rules = [
            'label_en' => ['required', 'string', 'max:120'],
            'label_ar' => ['required', 'string', 'max:120'],
            'description_en' => ['nullable', 'string', 'max:500'],
            'description_ar' => ['nullable', 'string', 'max:500'],
            'command' => ['required', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'parameters_json' => ['nullable', 'string', 'max:2000'],
        ];

        if ($existingKey === null) {
            $rules['slug'] = ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9\-]*$/'];
        }

        $validated = validator($input, $rules, [], [
            'slug' => __('scheduled_tasks.field_slug'),
            'label_en' => __('scheduled_tasks.field_label_en'),
            'label_ar' => __('scheduled_tasks.field_label_ar'),
            'command' => __('scheduled_tasks.field_command'),
        ])->validate();

        if (! $this->isAllowedCommand($validated['command'])) {
            throw ValidationException::withMessages([
                'command' => __('scheduled_tasks.invalid_command'),
            ]);
        }

        if (! empty($validated['parameters_json'])) {
            $parameters = json_decode($validated['parameters_json'], true);
            if (! is_array($parameters)) {
                throw ValidationException::withMessages([
                    'parameters_json' => __('scheduled_tasks.invalid_parameters_json'),
                ]);
            }
            $validated['parameters'] = $parameters;
        }

        return $validated;
    }

    private function isAllowedCommand(string $command): bool
    {
        if (! preg_match('/^[a-z][a-z0-9:\-]*$/', $command)) {
            return false;
        }

        $blocked = config('scheduled_tasks.blocked_commands', []);

        return ! in_array($command, $blocked, true)
            && array_key_exists($command, Artisan::all());
    }

    private function uniqueCustomTaskKey(string $slug): string
    {
        $base = 'custom.'.$slug;
        $key = $base;
        $counter = 2;

        while ($this->hasTask($key)) {
            $key = $base.'-'.$counter;
            $counter++;
        }

        return $key;
    }

    /** @return array<string, array<string, mixed>> */
    private function customDefinitionsFromDatabase(): array
    {
        if (! $this->definitionsSchemaReady()) {
            return [];
        }

        return ScheduledTaskDefinition::query()
            ->orderBy('task_key')
            ->get()
            ->mapWithKeys(fn (ScheduledTaskDefinition $row) => [
                $row->task_key => $row->toTaskDefinition(),
            ])
            ->all();
    }

    private function customDefinitionModel(string $key): ?ScheduledTaskDefinition
    {
        if (! $this->definitionsSchemaReady()) {
            return null;
        }

        return ScheduledTaskDefinition::query()->where('task_key', $key)->first();
    }

    private function buildEvent(Schedule $schedule, string $key, array $definition): ?Event
    {
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

        $cron = $this->resolveCronExpression($key, $definition);
        if ($cron) {
            $event->cron($cron);
        } else {
            $this->applyDefaultSchedule($event, $definition['schedule'] ?? []);
        }

        $timezone = $this->resolveEventTimezone($key, $definition);
        if ($timezone) {
            $event->timezone($timezone);
        }

        return $event;
    }

    private function resolveCronExpression(string $key, array $definition): ?string
    {
        if ($definition['is_custom'] ?? false) {
            return $this->customDefinitionModel($key)?->cron_expression;
        }

        return $this->settingFor($key)?->cron_expression;
    }

    private function resolveEventTimezone(string $key, array $definition): ?string
    {
        if (($definition['is_custom'] ?? false) && ($model = $this->customDefinitionModel($key)) && $model->timezone) {
            return $model->timezone;
        }

        return $this->resolveTimezone($definition['schedule'] ?? []);
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

    private function definitionsSchemaReady(): bool
    {
        return Schema::hasTable('scheduled_task_definitions');
    }

    private function assertDefinitionsSchemaReady(): void
    {
        abort_unless($this->definitionsSchemaReady(), 503);
    }
}

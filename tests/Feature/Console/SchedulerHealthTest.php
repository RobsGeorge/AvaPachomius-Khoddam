<?php

namespace Tests\Feature\Console;

use App\Console\Commands\EnsureSchedulerCronCommand;
use App\Models\ScheduledTaskDefinition;
use App\Models\ScheduledTaskRun;
use App\Services\ScheduledTaskRegistrar;
use App\Services\SchedulerHealthService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Tests\Support\EventModuleTestCase;

class SchedulerHealthTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelBack();
        Cache::flush();
    }

    public function test_heartbeat_task_is_registered_every_minute(): void
    {
        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);

        $event = collect($schedule->events())
            ->first(fn ($event) => $event->description === 'scheduler.heartbeat');

        $this->assertNotNull($event, 'scheduler.heartbeat must be registered');
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_health_is_stale_without_heartbeat_and_healthy_after_tick(): void
    {
        $health = app(SchedulerHealthService::class);

        $this->assertFalse($health->isHealthy());
        $this->assertNull($health->lastHeartbeatAt());

        $health->recordHeartbeat();
        $this->assertTrue($health->isHealthy());
        $this->assertNotNull($health->lastHeartbeatAt());

        $this->travel(6)->minutes();
        $this->assertFalse($health->isHealthy());
    }

    public function test_schedule_run_records_heartbeat_and_finishes_scheduled_command_runs(): void
    {
        ScheduledTaskDefinition::query()->create([
            'task_key' => 'custom.schedule-run-assert',
            'label_en' => 'Schedule run assert',
            'label_ar' => 'تحقق تشغيل الجدولة',
            'command' => 'inspire',
            'cron_expression' => '* * * * *',
            'enabled' => true,
            'created_by_id' => null,
            'updated_by_id' => null,
        ]);

        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);

        $heartbeat = collect($schedule->events())
            ->first(fn ($event) => $event->description === 'scheduler.heartbeat');
        $this->assertNotNull($heartbeat);
        $this->assertTrue($heartbeat->isDue($this->app));
        $heartbeat->run($this->app);

        $this->assertTrue(
            app(SchedulerHealthService::class)->isHealthy(),
            'running the heartbeat event must tick health'
        );

        // Exercise the production before/onSuccess path without spawning an OS
        // subprocess (Windows PHPUnit + artisan child can access-violate here).
        $runner = app(\App\Services\ScheduledTaskRunner::class);
        $runner->beginScheduledRun('custom.schedule-run-assert');
        app()->forgetInstance(\App\Services\ScheduledTaskRunner::class);
        app(\App\Services\ScheduledTaskRunner::class)->finishScheduledRun(
            'custom.schedule-run-assert',
            true,
            'Inspiring quote.',
            0
        );

        $run = ScheduledTaskRun::query()
            ->where('task_key', 'custom.schedule-run-assert')
            ->orderByDesc('run_id')
            ->first();

        $this->assertNotNull($run, 'scheduled command must create a run row');
        $this->assertSame(ScheduledTaskRun::TRIGGER_SCHEDULED, $run->trigger);
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(0, $run->exit_code);

        $heartbeatRuns = ScheduledTaskRun::query()
            ->where('task_key', 'scheduler.heartbeat')
            ->count();
        $this->assertSame(0, $heartbeatRuns, 'heartbeat must not flood scheduled_task_runs');
    }

    public function test_finish_scheduled_run_does_not_rely_on_instance_state(): void
    {
        $run = ScheduledTaskRun::query()->create([
            'task_key' => 'notifications.scan_events',
            'status' => ScheduledTaskRun::STATUS_RUNNING,
            'trigger' => ScheduledTaskRun::TRIGGER_SCHEDULED,
            'started_at' => now()->subSecond(),
        ]);

        // Fresh resolution mimics before/onSuccess resolving separate instances
        // before the singleton fix; finish must still close the row by task_key.
        app()->forgetInstance(\App\Services\ScheduledTaskRunner::class);
        app(\App\Services\ScheduledTaskRunner::class)->finishScheduledRun(
            'notifications.scan_events',
            true,
            'Finished.',
            0
        );

        $run->refresh();
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertSame('Finished.', $run->output);
    }

    public function test_ensure_cron_dry_run_prints_schedule_run_line(): void
    {
        $command = $this->app->make(EnsureSchedulerCronCommand::class);
        $line = $command->buildCronLine('php8.2');

        $this->assertStringContainsString('artisan schedule:run', $line);
        $this->assertMatchesRegularExpression('/\*\s+\*\s+\*\s+\*\s+\*/', $line);
        $this->assertStringContainsString(basename(base_path()), $line);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ScheduledTaskRun;
use App\Models\ScheduledTaskSetting;
use App\Models\User;
use App\Services\ScheduledTaskRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Support\EventModuleTestCase;

class ScheduledTaskAdminTest extends EventModuleTestCase
{
    private function superadmin(): User
    {
        return $this->createUser([
            'is_superadmin' => true,
            'email' => 'scheduled-tasks-super@example.com',
            'registration_completed' => true,
        ]);
    }

    public function test_superadmin_can_view_scheduled_tasks_dashboard(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('superadmin.scheduled-tasks.index'))
            ->assertOk()
            ->assertSee(__('scheduled_tasks.dashboard'), false)
            ->assertSee(__('scheduled_tasks.tasks.birthdays_notify_daily'), false)
            ->assertSee(__('scheduled_tasks.run_now'), false);
    }

    public function test_non_superadmin_cannot_access_scheduled_tasks_dashboard(): void
    {
        $user = $this->createUser(['email' => 'scheduled-tasks-user@example.com']);

        $this->actingAs($user)
            ->get(route('superadmin.scheduled-tasks.index'))
            ->assertForbidden();
    }

    public function test_manual_run_records_output_and_marks_success(): void
    {
        $super = $this->superadmin();

        $this->actingAs($super)
            ->post(route('superadmin.scheduled-tasks.run', 'notifications.scan_events'))
            ->assertRedirect();

        $run = ScheduledTaskRun::query()
            ->where('task_key', 'notifications.scan_events')
            ->latest('run_id')
            ->first();

        $this->assertNotNull($run);
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(ScheduledTaskRun::TRIGGER_MANUAL, $run->trigger);
        $this->assertSame($super->user_id, $run->triggered_by_id);
        $this->assertNotNull($run->finished_at);
    }

    public function test_settings_update_can_disable_task_registration(): void
    {
        $super = $this->superadmin();

        ScheduledTaskSetting::query()->updateOrCreate(
            ['task_key' => 'birthdays.notify_daily'],
            ['enabled' => false, 'updated_by_id' => $super->user_id]
        );

        $this->actingAs($super)
            ->from(route('superadmin.scheduled-tasks.index'))
            ->post(route('superadmin.scheduled-tasks.settings', 'birthdays.notify_daily'), [
                'cron_expression' => '',
            ])
            ->assertRedirect(route('superadmin.scheduled-tasks.index'))
            ->assertSessionHas('success');

        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);

        $events = collect($schedule->events())
            ->filter(fn ($event) => $event->description === 'birthdays.notify_daily');

        $this->assertCount(0, $events);
    }

    public function test_settings_update_accepts_valid_cron_override(): void
    {
        $super = $this->superadmin();

        $this->actingAs($super)
            ->from(route('superadmin.scheduled-tasks.index'))
            ->post(route('superadmin.scheduled-tasks.settings', 'birthdays.notify_daily'), [
                'enabled' => '1',
                'cron_expression' => '10 1 * * *',
            ])
            ->assertRedirect(route('superadmin.scheduled-tasks.index'))
            ->assertSessionHas('success');

        $setting = ScheduledTaskSetting::query()->where('task_key', 'birthdays.notify_daily')->first();
        $this->assertSame('10 1 * * *', $setting?->cron_expression);

        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);
        $event = collect($schedule->events())->first(fn ($event) => $event->description === 'birthdays.notify_daily');

        $this->assertNotNull($event);
        $this->assertSame('10 1 * * *', $event->expression);
    }

    public function test_run_detail_page_shows_output(): void
    {
        $super = $this->superadmin();

        $run = ScheduledTaskRun::query()->create([
            'task_key' => 'birthdays.notify_daily',
            'status' => ScheduledTaskRun::STATUS_SUCCESS,
            'trigger' => ScheduledTaskRun::TRIGGER_MANUAL,
            'exit_code' => 0,
            'duration_ms' => 42,
            'output' => 'Done. 1 course(s), 2 email(s), 2 portal notification(s).',
            'started_at' => now()->subSeconds(1),
            'finished_at' => now(),
            'triggered_by_id' => $super->user_id,
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.scheduled-tasks.show', $run))
            ->assertOk()
            ->assertSee('Done. 1 course(s)', false)
            ->assertSee(__('scheduled_tasks.tasks.birthdays_notify_daily'), false);
    }
}

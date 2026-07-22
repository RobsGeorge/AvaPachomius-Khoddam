<?php

namespace Tests\Feature;

use App\Models\ScheduledTaskDefinition;
use App\Models\ScheduledTaskRun;
use App\Models\ScheduledTaskSetting;
use App\Models\User;
use App\Services\ScheduledTaskRegistrar;
use App\Services\ScheduledTaskReportService;
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

    /** @param array<string, mixed> $overrides */
    private function customTaskPayload(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'test-list-schedule',
            'label_en' => 'List schedule test',
            'label_ar' => 'اختبار قائمة الجدولة',
            'command' => 'schedule:list',
            'schedule_frequency' => 'daily_at',
            'schedule_time' => '03:00',
            'enabled' => '1',
        ], $overrides);
    }

    private function scheduleSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'schedule_frequency' => 'daily_at',
            'schedule_time' => '01:10',
        ], $overrides);
    }

    private function expandUrl(string $taskKey): string
    {
        return route('superadmin.scheduled-tasks.index').'?expand='.urlencode(
            app(ScheduledTaskReportService::class)->taskExpandKey($taskKey)
        );
    }

    public function test_superadmin_can_view_scheduled_tasks_dashboard(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('superadmin.scheduled-tasks.index'))
            ->assertOk()
            ->assertSee(__('scheduled_tasks.dashboard'), false)
            ->assertSee(__('scheduled_tasks.tasks.birthdays_notify_daily'), false)
            ->assertSee(__('scheduled_tasks.run_now'), false)
            ->assertSee(__('scheduled_tasks.create_custom'), false)
            ->assertSee(__('scheduled_tasks.execution_report'), false);
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
        $this->assertIsArray($run->metadata['impact'] ?? null);
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
            ->post(route('superadmin.scheduled-tasks.settings', 'birthdays.notify_daily'), $this->scheduleSettingsPayload())
            ->assertRedirect($this->expandUrl('birthdays.notify_daily'))
            ->assertSessionHas('success');

        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);

        $events = collect($schedule->events())
            ->filter(fn ($event) => $event->description === 'birthdays.notify_daily');

        $this->assertCount(0, $events);
    }

    public function test_settings_update_accepts_valid_schedule_override(): void
    {
        $super = $this->superadmin();

        $this->actingAs($super)
            ->from(route('superadmin.scheduled-tasks.index'))
            ->post(route('superadmin.scheduled-tasks.settings', 'birthdays.notify_daily'), $this->scheduleSettingsPayload([
                'enabled' => '1',
                'schedule_time' => '01:10',
            ]))
            ->assertRedirect($this->expandUrl('birthdays.notify_daily'))
            ->assertSessionHas('success');

        $setting = ScheduledTaskSetting::query()->where('task_key', 'birthdays.notify_daily')->first();
        $this->assertSame('10 1 * * *', $setting?->cron_expression);

        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);
        $event = collect($schedule->events())->first(fn ($event) => $event->description === 'birthdays.notify_daily');

        $this->assertNotNull($event);
        $this->assertSame('10 1 * * *', $event->expression);
    }

    public function test_run_detail_page_shows_output_and_impact(): void
    {
        $super = $this->superadmin();

        $run = ScheduledTaskRun::query()->create([
            'task_key' => 'birthdays.notify_daily',
            'status' => ScheduledTaskRun::STATUS_SUCCESS,
            'trigger' => ScheduledTaskRun::TRIGGER_MANUAL,
            'exit_code' => 0,
            'duration_ms' => 42,
            'output' => 'Done. 1 course(s), 2 email(s), 2 portal notification(s).',
            'metadata' => [
                'impact' => [
                    'courses' => 1,
                    'emails' => 2,
                    'portal_notifications' => 2,
                ],
            ],
            'started_at' => now()->subSeconds(1),
            'finished_at' => now(),
            'triggered_by_id' => $super->user_id,
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.scheduled-tasks.show', $run))
            ->assertOk()
            ->assertSee('Done. 1 course(s)', false)
            ->assertSee(__('scheduled_tasks.tasks.birthdays_notify_daily'), false)
            ->assertSee(__('scheduled_tasks.impact'), false)
            ->assertSee(__('scheduled_tasks.impact_metric_courses'), false)
            ->assertSee('1', false);
    }

    public function test_superadmin_can_create_custom_task(): void
    {
        $super = $this->superadmin();

        $this->actingAs($super)
            ->from(route('superadmin.scheduled-tasks.index'))
            ->post(route('superadmin.scheduled-tasks.store'), $this->customTaskPayload())
            ->assertRedirect($this->expandUrl('custom.test-list-schedule'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('scheduled_task_definitions', [
            'task_key' => 'custom.test-list-schedule',
            'command' => 'schedule:list',
            'cron_expression' => '0 3 * * *',
            'created_by_id' => $super->user_id,
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.scheduled-tasks.index'))
            ->assertOk()
            ->assertSee('اختبار قائمة الجدولة', false)
            ->assertSee(__('scheduled_tasks.custom_badge'), false);
    }

    public function test_custom_task_registers_with_cron_expression(): void
    {
        $super = $this->superadmin();

        $this->actingAs($super)
            ->post(route('superadmin.scheduled-tasks.store'), $this->customTaskPayload());

        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);

        $event = collect($schedule->events())
            ->first(fn ($event) => $event->description === 'custom.test-list-schedule');

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
    }

    public function test_create_with_run_after_create_records_history_and_output(): void
    {
        $super = $this->superadmin();

        $response = $this->actingAs($super)
            ->post(route('superadmin.scheduled-tasks.store'), $this->customTaskPayload([
                'run_after_create' => '1',
            ]));

        $run = ScheduledTaskRun::query()
            ->where('task_key', 'custom.test-list-schedule')
            ->latest('run_id')
            ->first();

        $this->assertNotNull($run);
        $response->assertRedirect(route('superadmin.scheduled-tasks.show', $run));
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(ScheduledTaskRun::TRIGGER_MANUAL, $run->trigger);
        $this->assertSame($super->user_id, $run->triggered_by_id);

        $this->actingAs($super)
            ->get(route('superadmin.scheduled-tasks.show', $run))
            ->assertOk()
            ->assertSee('اختبار قائمة الجدولة', false);
    }

    public function test_manual_run_works_for_custom_task(): void
    {
        $super = $this->superadmin();

        ScheduledTaskDefinition::query()->create([
            'task_key' => 'custom.manual-run-test',
            'label_en' => 'Manual run test',
            'label_ar' => 'اختبار تشغيل يدوي',
            'command' => 'schedule:list',
            'cron_expression' => '15 4 * * *',
            'enabled' => true,
            'created_by_id' => $super->user_id,
            'updated_by_id' => $super->user_id,
        ]);

        $this->actingAs($super)
            ->post(route('superadmin.scheduled-tasks.run', 'custom.manual-run-test'))
            ->assertRedirect();

        $run = ScheduledTaskRun::query()
            ->where('task_key', 'custom.manual-run-test')
            ->latest('run_id')
            ->first();

        $this->assertNotNull($run);
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $run->status);
    }

    public function test_superadmin_can_delete_custom_task(): void
    {
        $super = $this->superadmin();

        $this->actingAs($super)
            ->post(route('superadmin.scheduled-tasks.store'), $this->customTaskPayload());

        $this->actingAs($super)
            ->delete(route('superadmin.scheduled-tasks.destroy', 'custom.test-list-schedule'))
            ->assertRedirect(route('superadmin.scheduled-tasks.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('scheduled_task_definitions', [
            'task_key' => 'custom.test-list-schedule',
        ]);

        $this->assertFalse(app(ScheduledTaskRegistrar::class)->hasTask('custom.test-list-schedule'));
    }

    public function test_create_rejects_blocked_command(): void
    {
        $this->actingAs($this->superadmin())
            ->from(route('superadmin.scheduled-tasks.index'))
            ->post(route('superadmin.scheduled-tasks.store'), $this->customTaskPayload([
                'command' => 'migrate',
            ]))
            ->assertSessionHasErrors('command');
    }

    public function test_create_rejects_invalid_schedule_frequency(): void
    {
        $this->actingAs($this->superadmin())
            ->from(route('superadmin.scheduled-tasks.index'))
            ->post(route('superadmin.scheduled-tasks.store'), $this->customTaskPayload([
                'schedule_frequency' => 'not-a-frequency',
            ]))
            ->assertSessionHasErrors('schedule_frequency');
    }
}

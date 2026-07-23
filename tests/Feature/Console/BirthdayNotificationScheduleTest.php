<?php

namespace Tests\Feature\Console;

use App\Services\ScheduledTaskRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class BirthdayNotificationScheduleTest extends TestCase
{
    public function test_daily_birthday_notifications_are_scheduled_at_start_of_day(): void
    {
        $timezone = config('attendance.timezone', config('app.timezone'));

        $schedule = new Schedule;
        app(ScheduledTaskRegistrar::class)->register($schedule);

        $events = collect($schedule->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'birthdays:notify-daily'));

        $this->assertCount(1, $events, 'birthdays:notify-daily must be registered in the scheduler');

        $event = $events->first();

        $this->assertSame('5 0 * * *', $event->expression);
        $this->assertSame($timezone, $event->timezone);
    }
}

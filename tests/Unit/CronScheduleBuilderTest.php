<?php

namespace Tests\Unit;

use App\Services\CronScheduleBuilder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CronScheduleBuilderTest extends TestCase
{
    private CronScheduleBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new CronScheduleBuilder;
    }

    public function test_builds_every_five_minutes_cron(): void
    {
        $this->assertSame('*/5 * * * *', $this->builder->build([
            'frequency' => CronScheduleBuilder::FREQUENCY_EVERY_FIVE_MINUTES,
        ]));
    }

    public function test_builds_daily_at_cron(): void
    {
        $this->assertSame('30 3 * * *', $this->builder->build([
            'frequency' => CronScheduleBuilder::FREQUENCY_DAILY_AT,
            'time' => '03:30',
        ]));
    }

    public function test_builds_weekly_on_cron(): void
    {
        $this->assertSame('0 9 * * 1', $this->builder->build([
            'frequency' => CronScheduleBuilder::FREQUENCY_WEEKLY_ON,
            'time' => '09:00',
            'day' => 1,
        ]));
    }

    public function test_builds_monthly_on_cron(): void
    {
        $this->assertSame('15 8 1 * *', $this->builder->build([
            'frequency' => CronScheduleBuilder::FREQUENCY_MONTHLY_ON,
            'time' => '08:15',
            'day' => 1,
        ]));
    }

    public function test_build_from_ui_field_names(): void
    {
        $this->assertSame('0 3 * * *', $this->builder->buildFromInput([
            'schedule_frequency' => CronScheduleBuilder::FREQUENCY_DAILY_AT,
            'schedule_time' => '03:00',
        ]));
    }

    public function test_parses_daily_at_expression(): void
    {
        $this->assertSame([
            'frequency' => CronScheduleBuilder::FREQUENCY_DAILY_AT,
            'time' => '03:00',
        ], $this->builder->parse('0 3 * * *'));
    }

    public function test_parses_weekly_on_expression(): void
    {
        $this->assertSame([
            'frequency' => CronScheduleBuilder::FREQUENCY_WEEKLY_ON,
            'time' => '09:00',
            'day' => 1,
        ], $this->builder->parse('0 9 * * 1'));
    }

    public function test_validate_schedule_input_requires_time_for_daily(): void
    {
        $this->expectException(ValidationException::class);

        $this->builder->validateScheduleInput([
            'schedule_frequency' => CronScheduleBuilder::FREQUENCY_DAILY_AT,
        ]);
    }

    public function test_validate_schedule_input_rejects_invalid_frequency(): void
    {
        $this->expectException(ValidationException::class);

        $this->builder->validateScheduleInput([
            'schedule_frequency' => 'not-a-frequency',
            'schedule_time' => '03:00',
        ]);
    }

    public function test_describe_daily_schedule(): void
    {
        $label = $this->builder->describe([
            'frequency' => CronScheduleBuilder::FREQUENCY_DAILY_AT,
            'time' => '03:00',
        ]);

        $this->assertSame(__('scheduled_tasks.freq_daily_at', ['time' => '03:00']), $label);
    }
}

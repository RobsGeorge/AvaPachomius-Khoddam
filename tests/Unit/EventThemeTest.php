<?php

namespace Tests\Unit;

use App\Support\EventTheme;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class EventThemeTest extends TestCase
{
    public function test_manual_override_activates_regardless_of_schedule(): void
    {
        $config = EventTheme::fromSettings(['event_theme' => ['enabled_manual' => true, 'periods' => []]]);

        $this->assertTrue(EventTheme::isActive($config, Carbon::parse('2026-01-01')));
    }

    public function test_inactive_when_off_and_no_periods(): void
    {
        $this->assertFalse(EventTheme::isActive(EventTheme::defaults(), Carbon::parse('2026-01-01')));
    }

    public function test_scheduled_period_activates_within_range_inclusive(): void
    {
        $config = EventTheme::fromSettings(['event_theme' => [
            'enabled_manual' => false,
            'periods' => [['start' => '2026-01-06', 'end' => '2026-01-08', 'label' => 'Nativity']],
        ]]);

        $this->assertTrue(EventTheme::isActive($config, Carbon::parse('2026-01-06')));
        $this->assertTrue(EventTheme::isActive($config, Carbon::parse('2026-01-07')));
        $this->assertTrue(EventTheme::isActive($config, Carbon::parse('2026-01-08')));
    }

    public function test_scheduled_period_inactive_outside_range(): void
    {
        $config = EventTheme::fromSettings(['event_theme' => [
            'enabled_manual' => false,
            'periods' => [['start' => '2026-01-06', 'end' => '2026-01-08', 'label' => 'Nativity']],
        ]]);

        $this->assertFalse(EventTheme::isActive($config, Carbon::parse('2026-01-05')));
        $this->assertFalse(EventTheme::isActive($config, Carbon::parse('2026-01-09')));
    }

    public function test_from_settings_drops_invalid_periods(): void
    {
        $config = EventTheme::fromSettings(['event_theme' => ['periods' => [
            ['start' => 'not-a-date', 'end' => '2026-01-08'],
            ['start' => '2026-02-10', 'end' => '2026-02-01'], // end before start
            'garbage',
            ['start' => '2026-03-01', 'end' => '2026-03-05', 'label' => 'Ok'],
        ]]]);

        $this->assertCount(1, $config['periods']);
        $this->assertSame('2026-03-01', $config['periods'][0]['start']);
        $this->assertSame('Ok', $config['periods'][0]['label']);
    }

    public function test_from_settings_defaults_when_missing(): void
    {
        $config = EventTheme::fromSettings(null);

        $this->assertFalse($config['enabled_manual']);
        $this->assertSame([], $config['periods']);
    }

    public function test_normalize_input_coerces_manual_flag(): void
    {
        $this->assertTrue(EventTheme::normalizeInput(['enabled_manual' => '1'])['enabled_manual']);
        $this->assertFalse(EventTheme::normalizeInput(['enabled_manual' => '0'])['enabled_manual']);
        $this->assertFalse(EventTheme::normalizeInput([])['enabled_manual']);
    }

    public function test_periods_are_capped(): void
    {
        $periods = [];
        for ($i = 0; $i < EventTheme::MAX_PERIODS + 5; $i++) {
            $periods[] = ['start' => '2026-01-01', 'end' => '2026-01-02', 'label' => 'x'];
        }

        $config = EventTheme::normalizeInput(['periods' => $periods]);

        $this->assertCount(EventTheme::MAX_PERIODS, $config['periods']);
    }
}

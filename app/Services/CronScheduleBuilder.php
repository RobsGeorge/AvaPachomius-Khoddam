<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CronScheduleBuilder
{
    public const FREQUENCY_EVERY_FIVE_MINUTES = 'every_five_minutes';

    public const FREQUENCY_HOURLY = 'hourly';

    public const FREQUENCY_DAILY_AT = 'daily_at';

    public const FREQUENCY_WEEKLY_ON = 'weekly_on';

    public const FREQUENCY_MONTHLY_ON = 'monthly_on';

    /** @return list<string> */
    public function frequencies(): array
    {
        return [
            self::FREQUENCY_EVERY_FIVE_MINUTES,
            self::FREQUENCY_HOURLY,
            self::FREQUENCY_DAILY_AT,
            self::FREQUENCY_WEEKLY_ON,
            self::FREQUENCY_MONTHLY_ON,
        ];
    }

    /** @param array<string, mixed> $input */
    public function buildFromInput(array $input): string
    {
        return $this->build($this->normalizeScheduleUi($input));
    }

    /** @param array<string, mixed> $schedule */
    public function build(array $schedule): string
    {
        $frequency = (string) ($schedule['frequency'] ?? self::FREQUENCY_DAILY_AT);

        return match ($frequency) {
            self::FREQUENCY_EVERY_FIVE_MINUTES => '*/5 * * * *',
            self::FREQUENCY_HOURLY => '0 * * * *',
            self::FREQUENCY_DAILY_AT => sprintf(
                '%d %d * * *',
                $this->minuteFromTime((string) ($schedule['time'] ?? '00:00')),
                $this->hourFromTime((string) ($schedule['time'] ?? '00:00')),
            ),
            self::FREQUENCY_WEEKLY_ON => sprintf(
                '%d %d * * %d',
                $this->minuteFromTime((string) ($schedule['time'] ?? '00:00')),
                $this->hourFromTime((string) ($schedule['time'] ?? '00:00')),
                (int) ($schedule['day'] ?? 1),
            ),
            self::FREQUENCY_MONTHLY_ON => sprintf(
                '%d %d %d * *',
                $this->minuteFromTime((string) ($schedule['time'] ?? '00:00')),
                $this->hourFromTime((string) ($schedule['time'] ?? '00:00')),
                (int) ($schedule['day'] ?? 1),
            ),
            default => '0 0 * * *',
        };
    }

    /** @return array{frequency: string, time?: string, day?: int} */
    public function parse(string $cronExpression): array
    {
        $parts = preg_split('/\s+/', trim($cronExpression)) ?: [];

        if (count($parts) !== 5) {
            return $this->defaultScheduleUi();
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        if ($minute === '*/5' && $hour === '*' && $dayOfMonth === '*' && $month === '*' && $dayOfWeek === '*') {
            return ['frequency' => self::FREQUENCY_EVERY_FIVE_MINUTES];
        }

        if ($this->isNumeric($minute)
            && $this->isWildcard($hour)
            && $this->isWildcard($dayOfMonth)
            && $this->isWildcard($month)
            && $this->isWildcard($dayOfWeek)
        ) {
            return ['frequency' => self::FREQUENCY_HOURLY];
        }

        if ($this->isNumeric($minute)
            && $this->isNumeric($hour)
            && $this->isWildcard($dayOfMonth)
            && $this->isWildcard($month)
            && $this->isWildcard($dayOfWeek)
        ) {
            return [
                'frequency' => self::FREQUENCY_DAILY_AT,
                'time' => $this->formatTime((int) $hour, (int) $minute),
            ];
        }

        if ($this->isNumeric($minute)
            && $this->isNumeric($hour)
            && $this->isWildcard($dayOfMonth)
            && $this->isWildcard($month)
            && $this->isNumeric($dayOfWeek)
        ) {
            return [
                'frequency' => self::FREQUENCY_WEEKLY_ON,
                'time' => $this->formatTime((int) $hour, (int) $minute),
                'day' => (int) $dayOfWeek,
            ];
        }

        if ($this->isNumeric($minute)
            && $this->isNumeric($hour)
            && $this->isNumeric($dayOfMonth)
            && $this->isWildcard($month)
            && $this->isWildcard($dayOfWeek)
        ) {
            return [
                'frequency' => self::FREQUENCY_MONTHLY_ON,
                'time' => $this->formatTime((int) $hour, (int) $minute),
                'day' => (int) $dayOfMonth,
            ];
        }

        return $this->defaultScheduleUi();
    }

    /** @param array<string, mixed> $schedule */
    public function describe(array $schedule): string
    {
        $ui = $this->normalizeScheduleUi($schedule);

        return match ($ui['frequency']) {
            self::FREQUENCY_EVERY_FIVE_MINUTES => __('scheduled_tasks.freq_every_five_minutes'),
            self::FREQUENCY_HOURLY => __('scheduled_tasks.freq_hourly'),
            self::FREQUENCY_DAILY_AT => __('scheduled_tasks.freq_daily_at', [
                'time' => $this->formatTimeForDisplay((string) $ui['time']),
            ]),
            self::FREQUENCY_WEEKLY_ON => __('scheduled_tasks.freq_weekly_on', [
                'day' => $this->weekdayLabel((int) $ui['day']),
                'time' => $this->formatTimeForDisplay((string) $ui['time']),
            ]),
            self::FREQUENCY_MONTHLY_ON => __('scheduled_tasks.freq_monthly_on', [
                'day' => (int) $ui['day'],
                'time' => $this->formatTimeForDisplay((string) $ui['time']),
            ]),
            default => __('scheduled_tasks.freq_daily_at', ['time' => $this->formatTimeForDisplay('00:00')]),
        };
    }

    /** @param array<string, mixed> $scheduleConfig */
    public function scheduleUiFromConfig(array $scheduleConfig): array
    {
        $frequency = (string) ($scheduleConfig['frequency'] ?? self::FREQUENCY_DAILY_AT);
        $ui = ['frequency' => $frequency];

        if (in_array($frequency, [self::FREQUENCY_DAILY_AT, self::FREQUENCY_WEEKLY_ON, self::FREQUENCY_MONTHLY_ON], true)) {
            $ui['time'] = (string) ($scheduleConfig['time'] ?? '00:00');
        }

        if (in_array($frequency, [self::FREQUENCY_WEEKLY_ON, self::FREQUENCY_MONTHLY_ON], true)) {
            $ui['day'] = (int) ($scheduleConfig['day'] ?? 1);
        }

        return $ui;
    }

    /** @param array<string, mixed> $input */
    public function validateScheduleInput(array $input, string $prefix = 'schedule'): array
    {
        $frequencyKey = $prefix === 'schedule' ? 'schedule_frequency' : $prefix.'_frequency';
        $timeKey = $prefix === 'schedule' ? 'schedule_time' : $prefix.'_time';
        $dayKey = $prefix === 'schedule' ? 'schedule_day' : $prefix.'_day';

        $validated = validator($input, [
            $frequencyKey => ['required', 'string', Rule::in($this->frequencies())],
            $timeKey => ['nullable', 'date_format:H:i'],
            $dayKey => ['nullable', 'integer'],
        ], [], [
            $frequencyKey => __('scheduled_tasks.field_schedule_frequency'),
            $timeKey => __('scheduled_tasks.field_schedule_time'),
            $dayKey => __('scheduled_tasks.field_schedule_day'),
        ])->validate();

        $frequency = (string) $validated[$frequencyKey];

        if (in_array($frequency, [self::FREQUENCY_DAILY_AT, self::FREQUENCY_WEEKLY_ON, self::FREQUENCY_MONTHLY_ON], true)
            && empty($validated[$timeKey])
        ) {
            throw ValidationException::withMessages([
                $timeKey => __('scheduled_tasks.schedule_time_required'),
            ]);
        }

        if ($frequency === self::FREQUENCY_WEEKLY_ON) {
            $day = $validated[$dayKey] ?? null;
            if ($day === null || $day < 0 || $day > 6) {
                throw ValidationException::withMessages([
                    $dayKey => __('scheduled_tasks.schedule_weekday_required'),
                ]);
            }
        }

        if ($frequency === self::FREQUENCY_MONTHLY_ON) {
            $day = $validated[$dayKey] ?? null;
            if ($day === null || $day < 1 || $day > 31) {
                throw ValidationException::withMessages([
                    $dayKey => __('scheduled_tasks.schedule_month_day_required'),
                ]);
            }
        }

        return [
            'frequency' => $frequency,
            'time' => $validated[$timeKey] ?? '00:00',
            'day' => isset($validated[$dayKey]) ? (int) $validated[$dayKey] : null,
        ];
    }

    /** @return array{frequency: string, time: string, day: int} */
    public function defaultScheduleUi(): array
    {
        return [
            'frequency' => self::FREQUENCY_DAILY_AT,
            'time' => '00:00',
            'day' => 1,
        ];
    }

    /** @param array<string, mixed> $input */
    private function normalizeScheduleUi(array $input): array
    {
        if (isset($input['frequency'])) {
            $ui = [
                'frequency' => (string) $input['frequency'],
                'time' => (string) ($input['time'] ?? '00:00'),
                'day' => isset($input['day']) ? (int) $input['day'] : 1,
            ];
        } else {
            $ui = [
                'frequency' => (string) ($input['schedule_frequency'] ?? self::FREQUENCY_DAILY_AT),
                'time' => (string) ($input['schedule_time'] ?? '00:00'),
                'day' => isset($input['schedule_day']) ? (int) $input['schedule_day'] : 1,
            ];
        }

        if (! in_array($ui['frequency'], $this->frequencies(), true)) {
            $ui['frequency'] = self::FREQUENCY_DAILY_AT;
        }

        if (! in_array($ui['frequency'], [self::FREQUENCY_DAILY_AT, self::FREQUENCY_WEEKLY_ON, self::FREQUENCY_MONTHLY_ON], true)) {
            unset($ui['time']);
        }

        if (! in_array($ui['frequency'], [self::FREQUENCY_WEEKLY_ON, self::FREQUENCY_MONTHLY_ON], true)) {
            unset($ui['day']);
        }

        return $ui;
    }

    private function minuteFromTime(string $time): int
    {
        [, $minute] = array_pad(explode(':', $time, 2), 2, '0');

        return (int) $minute;
    }

    private function hourFromTime(string $time): int
    {
        [$hour] = array_pad(explode(':', $time, 2), 2, '0');

        return (int) $hour;
    }

    private function formatTime(int $hour, int $minute): string
    {
        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function formatTimeForDisplay(string $time): string
    {
        try {
            return Carbon::createFromFormat('H:i', $time)->format('H:i');
        } catch (\Throwable) {
            return $time;
        }
    }

    private function weekdayLabel(int $day): string
    {
        $labels = __('scheduled_tasks.weekdays');

        return is_array($labels) ? ($labels[$day] ?? (string) $day) : (string) $day;
    }

    private function isWildcard(string $part): bool
    {
        return $part === '*';
    }

    private function isNumeric(string $part): bool
    {
        return ctype_digit($part);
    }
}

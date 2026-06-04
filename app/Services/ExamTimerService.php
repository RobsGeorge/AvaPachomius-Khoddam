<?php

namespace App\Services;

use App\Models\ExamSchedule;

class ExamTimerService
{
    /**
     * Synchronized timer: all students share the same window based on schedule start + duration.
     *
     * @return array{
     *   server_now: string,
     *   starts_at: string,
     *   ends_at: string,
     *   remaining_seconds: int,
     *   has_started: bool,
     *   has_ended: bool,
     *   duration_minutes: int
     * }
     */
    public function state(ExamSchedule $schedule): array
    {
        $schedule->loadMissing('exam');
        $now = now();
        $startsAt = $schedule->scheduled_date;
        $endsAt = $schedule->endsAt();
        $remaining = max(0, $endsAt->getTimestamp() - $now->getTimestamp());

        return [
            'server_now'        => $now->toIso8601String(),
            'starts_at'         => $startsAt->toIso8601String(),
            'ends_at'           => $endsAt->toIso8601String(),
            'remaining_seconds' => $remaining,
            'has_started'       => $now->gte($startsAt),
            'has_ended'         => $now->gte($endsAt),
            'duration_minutes'  => (int) ($schedule->exam->duration_minutes ?? 0),
        ];
    }

    public function canEnter(ExamSchedule $schedule): bool
    {
        $schedule->loadMissing('exam');
        $exam = $schedule->exam;

        if (! $exam || ! $exam->isOnline() || ! $exam->is_published) {
            return false;
        }

        if (! $schedule->hasStarted()) {
            return false;
        }

        if ($schedule->hasEnded()) {
            return false;
        }

        if (! $exam->allow_late_entry && now()->gt($schedule->scheduled_date->copy()->addMinutes(5))) {
            return false;
        }

        return true;
    }
}

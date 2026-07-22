<?php

namespace App\Services;

class ScheduledTaskImpactParser
{
    /** @return array<string, int> */
    public function parse(?string $output): array
    {
        if ($output === null || trim($output) === '') {
            return [];
        }

        $impact = [];

        $patterns = [
            'courses' => '/(\d+)\s+course\(s\)/i',
            'emails' => '/(\d+)\s+email\(s\)(?:\s+sent)?/i',
            'portal_notifications' => '/(\d+)\s+portal notification\(s\)/i',
            'notifications' => '/Generated\s+(\d+)\s+/i',
            'reminders' => '/Fired\s+(\d+)\s+custom reminders/i',
            'absent_marked' => '/Marked\s+(\d+)\s+absent/i',
            'late_marked' => '/Marked\s+(\d+)\s+late/i',
            'sessions_closed' => '/Closed open sessions/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if ($key === 'sessions_closed') {
                if (preg_match($pattern, $output)) {
                    $impact[$key] = 1;
                }

                continue;
            }

            if (preg_match_all($pattern, $output, $matches) && ! empty($matches[1])) {
                $impact[$key] = array_sum(array_map('intval', $matches[1]));
            }
        }

        return $impact;
    }

    /** @param array<string, int> $impact */
    public function summarize(array $impact): ?string
    {
        if ($impact === []) {
            return null;
        }

        $parts = [];

        foreach ($this->orderedKeys() as $key) {
            if (! isset($impact[$key]) || $impact[$key] <= 0) {
                continue;
            }

            $parts[] = __('scheduled_tasks.impact_'.$key, ['count' => $impact[$key]]);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** @return list<string> */
    private function orderedKeys(): array
    {
        return [
            'courses',
            'emails',
            'portal_notifications',
            'notifications',
            'reminders',
            'absent_marked',
            'late_marked',
            'sessions_closed',
        ];
    }
}

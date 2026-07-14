<?php

namespace App\Services;

use App\Models\ExamSchedule;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * F-06 — builds a personal calendar feed (upcoming sessions, exams, and events)
 * for a user and renders it as an RFC 5545 iCalendar document, so date-critical
 * items can be subscribed to / imported into any calendar app. Composes existing
 * data only.
 */
class CalendarService
{
    public function __construct(
        private StudentRosterService $roster,
        private EventEligibilityService $eventEligibility,
    ) {}

    /**
     * @return list<array{uid:string,summary:string,start:Carbon,end:?Carbon,all_day:bool,location:?string,description:?string}>
     */
    public function itemsForUser(User $user): array
    {
        return collect()
            ->merge($this->examItems($user))
            ->merge($this->sessionItems($user))
            ->merge($this->eventItems($user))
            ->sortBy(fn ($item) => $item['start']->getTimestamp())
            ->values()
            ->all();
    }

    /** Render the user's calendar as an iCalendar (.ics) document. */
    public function icsForUser(User $user): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Khedma//Portal//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($this->itemsForUser($user) as $item) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$item['uid'];
            $lines[] = 'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z');

            if ($item['all_day']) {
                $lines[] = 'DTSTART;VALUE=DATE:'.$item['start']->format('Ymd');
            } else {
                $lines[] = 'DTSTART:'.$item['start']->clone()->utc()->format('Ymd\THis\Z');
                if ($item['end']) {
                    $lines[] = 'DTEND:'.$item['end']->clone()->utc()->format('Ymd\THis\Z');
                }
            }

            $lines[] = 'SUMMARY:'.$this->escape($item['summary']);
            if (filled($item['location'])) {
                $lines[] = 'LOCATION:'.$this->escape($item['location']);
            }
            if (filled($item['description'])) {
                $lines[] = 'DESCRIPTION:'.$this->escape($item['description']);
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545 mandates CRLF line endings.
        return implode("\r\n", $lines)."\r\n";
    }

    /** Course IDs the user learns in or can administer. */
    private function relevantCourseIds(User $user): Collection
    {
        return $this->roster->studentEnrolledCourses($user)->pluck('course_id')
            ->merge($this->roster->accessibleCourses($user)->pluck('course_id'))
            ->unique()
            ->values();
    }

    private function examItems(User $user): Collection
    {
        $courseIds = $this->relevantCourseIds($user);
        if ($courseIds->isEmpty()) {
            return collect();
        }

        return ExamSchedule::query()
            ->whereHas('exam', fn ($q) => $q->whereIn('course_id', $courseIds)->where('is_published', true))
            ->where('scheduled_date', '>=', now())
            ->with('exam')
            ->orderBy('scheduled_date')
            ->get()
            ->map(function (ExamSchedule $schedule) {
                $start = $schedule->scheduled_date;
                $minutes = (int) ($schedule->exam?->duration_minutes ?? 0);

                return [
                    'uid' => 'exam-schedule-'.$schedule->schedule_id.'@khedma',
                    'summary' => __('calendar.exam_prefix').' '.($schedule->exam?->exam_name ?? ''),
                    'start' => $start,
                    'end' => $minutes > 0 ? $start->clone()->addMinutes($minutes) : null,
                    'all_day' => false,
                    'location' => null,
                    'description' => $schedule->exam?->course?->title,
                ];
            });
    }

    private function sessionItems(User $user): Collection
    {
        $courseIds = $this->relevantCourseIds($user);
        if ($courseIds->isEmpty()) {
            return collect();
        }

        return Session::query()
            ->whereIn('course_id', $courseIds)
            ->whereDate('session_date', '>=', now()->toDateString())
            ->with('course')
            ->orderBy('session_date')
            ->get()
            ->map(function (Session $session) {
                $time = trim((string) $session->session_start_time);
                $allDay = $time === '';
                $start = $allDay
                    ? $session->session_date->clone()->startOfDay()
                    : Carbon::parse($session->session_date->format('Y-m-d').' '.$time);

                return [
                    'uid' => 'session-'.$session->session_id.'@khedma',
                    'summary' => __('calendar.session_prefix').' '.($session->session_title ?? ''),
                    'start' => $start,
                    'end' => $allDay ? null : $start->clone()->addHours(2),
                    'all_day' => $allDay,
                    'location' => null,
                    'description' => $session->course?->title,
                ];
            });
    }

    private function eventItems(User $user): Collection
    {
        $events = $this->eventEligibility->visibleEvents($user);
        if (! $events instanceof Collection) {
            $events = collect($events);
        }

        return $events->map(fn ($event) => [
            'uid' => 'event-'.($event->event_id ?? $event->getKey()).'@khedma',
            'summary' => __('calendar.event_prefix').' '.$event->title,
            'start' => $event->starts_at,
            'end' => $event->ends_at,
            'all_day' => false,
            'location' => $event->location,
            'description' => $event->description,
        ])->filter(fn ($item) => $item['start'] !== null);
    }

    private function escape(?string $value): string
    {
        $value = (string) $value;
        // RFC 5545 text escaping: backslash, semicolon, comma, and newlines.
        $value = str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $value);

        return $value;
    }
}

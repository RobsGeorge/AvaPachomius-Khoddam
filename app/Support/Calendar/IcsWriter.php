<?php

namespace App\Support\Calendar;

/**
 * Renders a list of calendar items into an RFC 5545 iCalendar document.
 * Shared by any feed builder (academic F-06 feed, pastoral PAC5 feeds) so the
 * VEVENT formatting/escaping rules live in exactly one place.
 */
class IcsWriter
{
    /**
     * @param  list<array{uid:string,summary:string,start:\Illuminate\Support\Carbon,end:?\Illuminate\Support\Carbon,all_day:bool,location:?string,description:?string,rrule?:string}>  $items
     */
    public function render(array $items): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Khedma//Portal//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($items as $item) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$item['uid'];
            $lines[] = 'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z');

            if ($item['all_day']) {
                $lines[] = 'DTSTART;VALUE=DATE:'.$item['start']->format('Ymd');
                if (! empty($item['rrule'] ?? null)) {
                    $lines[] = 'RRULE:'.$item['rrule'];
                }
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

    private function escape(?string $value): string
    {
        $value = (string) $value;
        // RFC 5545 text escaping: backslash, semicolon, comma, and newlines.
        $value = str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $value);

        return $value;
    }
}

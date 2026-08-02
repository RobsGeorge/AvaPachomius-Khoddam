<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Reads the raw log files written by the server (storage/logs/*.log) so the
 * superadmin console can surface errors without SSH access.
 *
 * Parsing is deliberately shallow: the standard Laravel line prefix
 * `[2026-08-02 00:12:34] production.ERROR: message` is split into a timestamp,
 * a level and the message; everything that follows on subsequent lines (stack
 * traces, JSON context) is kept verbatim as the entry detail. Lines that do not
 * carry a timestamp prefix are still surfaced, unparsed.
 */
class ServerLogReader
{
    /** Production log files grow into hundreds of megabytes — only the tail is read. */
    public const TAIL_BYTES = 512 * 1024;

    /** Upper bound on entries handed to the UI, newest kept. */
    public const MAX_ENTRIES = 2000;

    private const ENTRY_PATTERN = '/^\[(?<time>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)\]\s*(?:(?<env>[^\s\.\[\]]+)\.(?<level>[A-Za-z]+)\s*:)?\s?(?<message>.*)$/';

    /** Severity order used to sort the level filter, most severe first. */
    private const LEVEL_ORDER = [
        'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
    ];

    /** Directory holding the server log files. */
    public function directory(): string
    {
        $configured = config('logging.channels.single.path');

        return is_string($configured) && $configured !== ''
            ? dirname($configured)
            : storage_path('logs');
    }

    /**
     * Log files available for viewing, most recently written first.
     *
     * @return list<array{name: string, size: int, modified_at: Carbon}>
     */
    public function availableFiles(): array
    {
        $directory = $this->directory();

        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (glob(rtrim($directory, '/').'/*.log') ?: [] as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $files[] = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'modified_at' => Carbon::createFromTimestamp((int) filemtime($path), config('app.timezone')),
            ];
        }

        usort($files, fn (array $a, array $b) => $b['modified_at']->getTimestamp() <=> $a['modified_at']->getTimestamp());

        return array_values($files);
    }

    /** Whether the given name is one of the readable log files (guards path traversal). */
    public function isReadableFile(?string $name): bool
    {
        if ($name === null || $name === '') {
            return false;
        }

        foreach ($this->availableFiles() as $file) {
            if ($file['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the tail of a log file and return its entries, newest first.
     *
     * @param  array{level?: string|null, q?: string|null}  $filters
     * @return array{
     *     entries: list<array{time: ?Carbon, level: ?string, environment: ?string, message: string, detail: ?string}>,
     *     level_counts: array<string, int>,
     *     total_scanned: int,
     *     truncated: bool,
     *     file_size: int,
     *     bytes_read: int
     * }
     */
    public function read(string $name, array $filters = []): array
    {
        $empty = [
            'entries' => [],
            'level_counts' => [],
            'total_scanned' => 0,
            'truncated' => false,
            'file_size' => 0,
            'bytes_read' => 0,
        ];

        if (! $this->isReadableFile($name)) {
            return $empty;
        }

        $path = rtrim($this->directory(), '/').'/'.basename($name);
        $size = (int) filesize($path);
        $truncated = $size > self::TAIL_BYTES;
        $contents = $this->tail($path, $size);

        if ($contents === '') {
            return array_merge($empty, ['file_size' => $size]);
        }

        $entries = $this->parse($contents, $truncated);
        $totalScanned = count($entries);

        if ($totalScanned > self::MAX_ENTRIES) {
            $entries = array_slice($entries, -self::MAX_ENTRIES);
            $truncated = true;
        }

        $levelCounts = $this->countLevels($entries);
        $entries = array_reverse($this->filter($entries, $filters));

        return [
            'entries' => array_values($entries),
            'level_counts' => $levelCounts,
            'total_scanned' => $totalScanned,
            'truncated' => $truncated,
            'file_size' => $size,
            'bytes_read' => strlen($contents),
        ];
    }

    /** Levels present in a set of counts, ordered by severity. */
    public function orderLevels(array $levelCounts): array
    {
        $levels = array_keys($levelCounts);

        usort($levels, function (string $a, string $b) {
            $ai = array_search($a, self::LEVEL_ORDER, true);
            $bi = array_search($b, self::LEVEL_ORDER, true);

            return ($ai === false ? PHP_INT_MAX : $ai) <=> ($bi === false ? PHP_INT_MAX : $bi);
        });

        return $levels;
    }

    /** Human readable byte size, e.g. "1.4 MB". */
    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, $value >= 10 ? 0 : 1).' '.$units[$unit];
    }

    /** Bootstrap contextual colour for a level badge. */
    public function levelVariant(?string $level): string
    {
        return match ($level) {
            'emergency', 'alert', 'critical', 'error' => 'danger',
            'warning' => 'warning',
            'notice', 'info' => 'info',
            'debug' => 'secondary',
            default => 'light',
        };
    }

    /** Read at most TAIL_BYTES from the end of the file. */
    private function tail(string $path, int $size): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        try {
            $length = min($size, self::TAIL_BYTES);

            if ($length <= 0) {
                return '';
            }

            fseek($handle, -$length, SEEK_END);
            $contents = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        return is_string($contents) ? $contents : '';
    }

    /**
     * @return list<array{time: ?Carbon, level: ?string, environment: ?string, message: string, detail: ?string}>
     */
    private function parse(string $contents, bool $tailWasCut): array
    {
        $entries = [];
        $preamble = [];
        $current = null;

        foreach (preg_split("/\r\n|\n|\r/", $contents) ?: [] as $line) {
            if (preg_match(self::ENTRY_PATTERN, $line, $matches) === 1) {
                if ($current !== null) {
                    $entries[] = $this->finalise($current);
                }

                $current = [
                    'time' => $this->parseTime($matches['time']),
                    'level' => isset($matches['level']) && $matches['level'] !== ''
                        ? strtolower($matches['level'])
                        : null,
                    'environment' => isset($matches['env']) && $matches['env'] !== '' ? $matches['env'] : null,
                    'message' => trim($matches['message']),
                    'detail' => [],
                ];

                continue;
            }

            if ($current !== null) {
                $current['detail'][] = $line;

                continue;
            }

            $preamble[] = $line;
        }

        if ($current !== null) {
            $entries[] = $this->finalise($current);
        }

        // Lines before the first timestamp are either a log entry the tail cut in half
        // (dropped) or output from a tool that does not use Laravel's format (kept).
        if (! $tailWasCut && $preamble !== []) {
            $unparsed = array_values(array_filter($preamble, fn (string $line) => trim($line) !== ''));

            $entries = array_merge(array_map(fn (string $line) => [
                'time' => null,
                'level' => null,
                'environment' => null,
                'message' => $line,
                'detail' => null,
            ], $unparsed), $entries);
        }

        return array_values($entries);
    }

    /** @param array{time: ?Carbon, level: ?string, environment: ?string, message: string, detail: list<string>} $entry */
    private function finalise(array $entry): array
    {
        $detail = rtrim(implode("\n", $entry['detail']));
        $entry['detail'] = trim($detail) === '' ? null : $detail;

        return $entry;
    }

    private function parseTime(string $raw): ?Carbon
    {
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param list<array<string, mixed>> $entries */
    private function countLevels(array $entries): array
    {
        $counts = [];

        foreach ($entries as $entry) {
            $level = $entry['level'] ?? 'unparsed';
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  array{level?: string|null, q?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    private function filter(array $entries, array $filters): array
    {
        $level = $filters['level'] ?? null;
        $term = $filters['q'] ?? null;

        if ($level === null && $term === null) {
            return $entries;
        }

        $term = is_string($term) && $term !== '' ? mb_strtolower($term) : null;

        return array_values(array_filter($entries, function (array $entry) use ($level, $term) {
            if ($level !== null && $level !== '' && ($entry['level'] ?? 'unparsed') !== $level) {
                return false;
            }

            if ($term !== null) {
                $haystack = mb_strtolower($entry['message'].' '.($entry['detail'] ?? ''));

                if (! str_contains($haystack, $term)) {
                    return false;
                }
            }

            return true;
        }));
    }
}

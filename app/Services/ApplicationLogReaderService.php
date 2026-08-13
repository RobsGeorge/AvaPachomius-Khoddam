<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Reads the raw log files written by the server (storage/logs/*.log) so the
 * superadmin console can surface errors without SSH access.
 *
 * Parsing is deliberately shallow: the standard Laravel line prefix
 * `[2026-08-02 00:12:34] production.ERROR: message` (and PHP's own
 * `[02-Aug-2026 00:12:34 UTC]` error_log prefix) is split into a timestamp, a
 * level and the message; everything that follows on subsequent lines (stack
 * traces, JSON context) is kept verbatim as the entry detail. Lines that do not
 * carry a timestamp prefix are still surfaced, unparsed, one per line — cron and
 * artisan output land here.
 */
class ApplicationLogReaderService
{
    /** Production log files grow into hundreds of megabytes — only the tail is read. */
    public const TAIL_BYTES = 512 * 1024;

    /** Upper bound on entries handed to the UI, newest kept. */
    public const MAX_ENTRIES = 2000;

    /** Monolog `2026-08-02 00:12:34(.123)(+02:00)` or PHP error_log `02-Aug-2026 00:12:34 UTC`. */
    private const TIMESTAMP = '\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?'
        .'|\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}(?:\s+[A-Za-z][\w\/+\-]*)?';

    private const ENTRY_PATTERN = '/^\[(?<time>'.self::TIMESTAMP.')\]\s*(?:(?<env>[^\s\.\[\]]+)\.(?<level>[A-Za-z]+)\s*:)?\s?(?<message>.*)$/';

    /** Severity order used to sort the level filter, most severe first. */
    private const LEVEL_ORDER = [
        'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
    ];

    /** Bucket key for entries whose line carries no `env.LEVEL:` prefix. */
    public const LEVEL_NONE = 'none';

    /** @var list<array{name: string, size: int, modified_at: Carbon}>|null */
    private ?array $files = null;

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
        if ($this->files !== null) {
            return $this->files;
        }

        $directory = realpath($this->directory());

        if ($directory === false || ! is_dir($directory)) {
            return $this->files = [];
        }

        $files = [];

        foreach (glob(rtrim($directory, '/').'/*.log') ?: [] as $path) {
            $real = realpath($path);

            // Containment check: a symlink dropped in the log directory must not
            // turn this page into an arbitrary file reader.
            if ($real === false || ! str_starts_with($real, $directory.DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (! is_file($real) || ! is_readable($real)) {
                continue;
            }

            // Rotation can unlink a file between the glob and the stat.
            $size = @filesize($real);
            $modified = @filemtime($real);

            if ($size === false || $modified === false) {
                continue;
            }

            $files[] = [
                // Name from the globbed path, not the resolved one: an in-directory
                // symlink must keep its own name instead of shadowing its target.
                'name' => basename($path),
                'size' => (int) $size,
                'modified_at' => Carbon::createFromTimestamp((int) $modified, config('app.timezone')),
            ];
        }

        usort($files, fn (array $a, array $b) => $b['modified_at']->getTimestamp() <=> $a['modified_at']->getTimestamp());

        return $this->files = array_values($files);
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
     *     truncated: bool
     * }
     */
    public function read(string $name, array $filters = []): array
    {
        $empty = [
            'entries' => [],
            'level_counts' => [],
            'total_scanned' => 0,
            'truncated' => false,
        ];

        if (! $this->isReadableFile($name)) {
            return $empty;
        }

        $path = rtrim($this->directory(), '/').'/'.basename($name);
        $size = (int) @filesize($path);
        $truncated = $size > self::TAIL_BYTES;
        $contents = $this->tail($path, $size);

        if ($contents === '') {
            return $empty;
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

    /** Whether a level name is one this reader can produce. */
    public function isKnownLevel(string $level): bool
    {
        return $level === self::LEVEL_NONE || in_array($level, self::LEVEL_ORDER, true);
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

            // A file rotated between the stat and the seek leaves the handle at
            // offset 0, which is the right place to start reading anyway.
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

        // Lines before the first timestamp come from a tool that does not use
        // Laravel's format (cron, artisan) and are surfaced one per line. Only the
        // very first line is dropped when the tail cut it in half.
        if ($tailWasCut) {
            array_shift($preamble);
        }

        $unparsed = array_values(array_filter($preamble, fn (string $line) => trim($line) !== ''));

        if ($unparsed !== []) {
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
            // error_log prefixes carry their own timezone; normalise so every row
            // in the table is on one clock.
            return Carbon::parse($raw)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param list<array<string, mixed>> $entries */
    private function countLevels(array $entries): array
    {
        $counts = [];

        foreach ($entries as $entry) {
            $level = $entry['level'] ?? self::LEVEL_NONE;
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
            if ($level !== null && $level !== '' && ($entry['level'] ?? self::LEVEL_NONE) !== $level) {
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

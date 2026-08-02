<?php

namespace App\Services;

/**
 * Reads Laravel/Monolog log files from storage and returns a lightweight
 * newest-first list of entries (timestamp + level + message). Stack traces
 * are omitted — only the header/message line is kept.
 */
class ServerLogReader
{
    public const DEFAULT_LIMIT = 200;

    /** @var list<string> */
    public const ERROR_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function __construct(
        private ?string $logsDirectory = null,
    ) {
        $this->logsDirectory = $logsDirectory ?? storage_path('logs');
    }

    /**
     * @param  list<string>|null  $levels  Uppercase level names; null = all levels.
     * @return list<array{time: string, level: string, message: string, file: string}>
     */
    public function recent(?int $limit = null, ?array $levels = self::ERROR_LEVELS): array
    {
        $limit = max(1, min($limit ?? self::DEFAULT_LIMIT, 500));
        $files = $this->logFiles();

        if ($files === []) {
            return [];
        }

        $entries = [];
        foreach ($files as $file) {
            foreach ($this->parseFile($file) as $entry) {
                if ($levels !== null && ! in_array($entry['level'], $levels, true)) {
                    continue;
                }
                $entries[] = $entry;
            }
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($b['time'], $a['time']));

        return array_slice($entries, 0, $limit);
    }

    /** @return list<string> Absolute paths, newest mtime first. */
    public function logFiles(): array
    {
        $dir = $this->logsDirectory;
        if (! is_dir($dir)) {
            return [];
        }

        $paths = glob($dir.DIRECTORY_SEPARATOR.'laravel*.log') ?: [];
        $paths = array_values(array_filter($paths, 'is_file'));

        usort($paths, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $paths;
    }

    /**
     * @return list<array{time: string, level: string, message: string, file: string}>
     */
    private function parseFile(string $path): array
    {
        $content = $this->readTail($path, 512 * 1024);
        if ($content === '') {
            return [];
        }

        $basename = basename($path);
        $entries = [];

        // Monolog single-line header: [2026-08-02 12:34:56] local.ERROR: message ...
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+\S+\.([A-Z]+):\s*(.*)$/m';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $message = trim($match[3] ?? '');
            // Drop trailing Monolog JSON context / exception blob (" {...") on the same line.
            if (preg_match('/^(.*?)\s\{"/u', $message, $msgParts) === 1) {
                $message = trim($msgParts[1]);
            }

            $entries[] = [
                'time' => $match[1],
                'level' => strtoupper($match[2]),
                'message' => $message,
                'file' => $basename,
            ];
        }

        return $entries;
    }

    /** Read up to $maxBytes from the end of the file. */
    private function readTail(string $path, int $maxBytes): string
    {
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            return '';
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        try {
            if ($size > $maxBytes) {
                fseek($handle, -$maxBytes, SEEK_END);
                // Discard possible partial first line after seek.
                fgets($handle);
            }

            $content = stream_get_contents($handle);

            return is_string($content) ? $content : '';
        } finally {
            fclose($handle);
        }
    }
}

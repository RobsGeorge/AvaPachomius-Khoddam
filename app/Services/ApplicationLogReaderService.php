<?php

namespace App\Services;

class ApplicationLogReaderService
{
    /** @var list<string> */
    private const ALLOWED_BASENAMES = [
        'laravel.log',
        'scheduler-cron.log',
    ];

    /**
     * @return array<string, string> basename => localized label key suffix
     */
    public function discoverLogFiles(): array
    {
        $dir = storage_path('logs');
        $files = [];

        foreach (self::ALLOWED_BASENAMES as $basename) {
            if (is_file($dir.'/'.$basename)) {
                $files[$basename] = $basename;
            }
        }

        foreach (glob($dir.'/laravel-*.log') ?: [] as $path) {
            $basename = basename($path);
            if ($this->isAllowedBasename($basename)) {
                $files[$basename] = $basename;
            }
        }

        krsort($files);

        return $files;
    }

    /**
     * @return array{
     *     entries: list<array{timestamp: ?string, level: ?string, message: string}>,
     *     file: string,
     *     missing: bool
     * }
     */
    public function tail(string $basename, int $maxLines = 200, ?string $levelFilter = null): array
    {
        $maxLines = max(10, min(500, $maxLines));
        $path = $this->resolvePath($basename);

        if ($path === null) {
            return [
                'entries' => [],
                'file' => $basename,
                'missing' => true,
            ];
        }

        $rawLines = $this->readLastLines($path, $maxLines * 3);
        $entries = $this->parseLines($rawLines);

        if ($levelFilter !== null && $levelFilter !== '') {
            $levelFilter = strtoupper($levelFilter);
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => strtoupper((string) ($entry['level'] ?? '')) === $levelFilter
            ));
        }

        $entries = array_slice($entries, -$maxLines);

        return [
            'entries' => $entries,
            'file' => $basename,
            'missing' => false,
        ];
    }

    public function defaultBasename(): string
    {
        $files = $this->discoverLogFiles();

        if ($files === []) {
            return 'laravel.log';
        }

        if (isset($files['laravel.log'])) {
            return 'laravel.log';
        }

        return array_key_first($files);
    }

    private function isAllowedBasename(string $basename): bool
    {
        if (in_array($basename, self::ALLOWED_BASENAMES, true)) {
            return true;
        }

        return (bool) preg_match('/^laravel-\d{4}-\d{2}-\d{2}\.log$/', $basename);
    }

    private function resolvePath(string $basename): ?string
    {
        if (! $this->isAllowedBasename($basename)) {
            return null;
        }

        $path = storage_path('logs/'.$basename);

        return is_file($path) ? $path : null;
    }

    /**
     * @return list<string>
     */
    private function readLastLines(string $path, int $maxLines): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $maxLines + 1);

        $lines = [];
        $file->seek($start);
        while (! $file->eof()) {
            $line = rtrim((string) $file->current(), "\r\n");
            if ($line !== '') {
                $lines[] = $line;
            }
            $file->next();
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{timestamp: ?string, level: ?string, message: string}>
     */
    private function parseLines(array $lines): array
    {
        $entries = [];
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)$/';

        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $matches)) {
                $entries[] = [
                    'timestamp' => $matches[1],
                    'level' => strtoupper($matches[2]),
                    'message' => $matches[3],
                ];

                continue;
            }

            if ($entries !== []) {
                $last = count($entries) - 1;
                $entries[$last]['message'] .= "\n".$line;
            }
        }

        return $entries;
    }
}

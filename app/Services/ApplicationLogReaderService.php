<?php

namespace App\Services;

class ApplicationLogReaderService
{
    /** Cap on how far back a page can force us to read, regardless of $page. */
    private const MAX_RAW_LINES = 5000;

    /**
     * @return array<string, string> basename => localized label key suffix
     */
    public function discoverLogFiles(): array
    {
        $dir = storage_path('logs');
        $files = [];

        // Any plain ".log" file directly under storage/logs — not just the historical
        // laravel.log / scheduler-cron.log / laravel-*.log names — so a custom queue
        // worker or job log dropped in the same directory shows up automatically.
        foreach (glob($dir.'/*.log') ?: [] as $path) {
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
     *     missing: bool,
     *     total: int,
     *     page: int,
     *     per_page: int
     * }
     */
    public function tail(
        string $basename,
        int $perPage = 200,
        ?string $levelFilter = null,
        ?string $search = null,
        int $page = 1,
    ): array {
        $perPage = max(10, min(500, $perPage));
        $page = max(1, $page);
        $path = $this->resolvePath($basename);

        if ($path === null) {
            return [
                'entries' => [],
                'file' => $basename,
                'missing' => true,
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
            ];
        }

        // Read enough of the file's tail to (likely) cover the requested page — the
        // window grows with $page so paging back reaches further into the file — but
        // capped so a very large page number can't force an unbounded read.
        $rawWindow = min(self::MAX_RAW_LINES, $perPage * $page * 3);
        $rawLines = $this->readLastLines($path, $rawWindow);
        $entries = $this->parseLines($rawLines);

        if ($levelFilter !== null && $levelFilter !== '') {
            $levelFilter = strtoupper($levelFilter);
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => strtoupper((string) ($entry['level'] ?? '')) === $levelFilter
            ));
        }

        if ($search !== null && $search !== '') {
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => stripos($entry['message'], $search) !== false
            ));
        }

        // Newest first, so page 1 shows the most recent matching entries and higher
        // pages page back through older ones (within the read window above).
        $entries = array_reverse($entries);

        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return [
            'entries' => array_slice($entries, ($page - 1) * $perPage, $perPage),
            'file' => $basename,
            'missing' => false,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
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

    /** A plain filename ending in ".log" — no path separators or traversal segments. */
    private function isAllowedBasename(string $basename): bool
    {
        if ($basename === '' || str_contains($basename, '/') || str_contains($basename, '\\') || str_contains($basename, '..')) {
            return false;
        }

        return (bool) preg_match('/^[\w.-]+\.log$/', $basename);
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

                continue;
            }

            $entries[] = [
                'timestamp' => null,
                'level' => null,
                'message' => $line,
            ];
        }

        return $entries;
    }
}

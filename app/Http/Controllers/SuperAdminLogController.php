<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Superadmin "server logs" reporting page. Reads Laravel log files straight off disk
 * and shows each entry's timestamp + raw message — no exception/JSON parsing, so it
 * always renders something useful even for malformed or unusual log lines.
 */
class SuperAdminLogController extends Controller
{
    /** Only read the tail of large files so a multi-GB log can't exhaust memory. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const PER_PAGE = 50;

    public function index(Request $request)
    {
        $directory = storage_path('logs');
        $files = $this->availableFiles($directory);

        $selectedFile = $request->query('file');
        if (! is_string($selectedFile) || ! in_array($selectedFile, $files, true)) {
            $selectedFile = $files[0] ?? null;
        }

        $level = strtoupper((string) $request->query('level', ''));
        $search = trim((string) $request->query('q', ''));

        $entries = $selectedFile
            ? $this->readEntries($directory.'/'.$selectedFile)
            : collect();

        $filtered = $entries
            ->when($level !== '', fn (Collection $rows) => $rows->filter(
                fn (array $entry) => $entry['level'] === $level
            ))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $entry) => Str::contains(Str::lower($entry['message']), Str::lower($search))
            ))
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $slice = $filtered->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values();

        $paginatedEntries = new LengthAwarePaginator(
            $slice,
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('superadmin.logs.index', [
            'files' => $files,
            'selectedFile' => $selectedFile,
            'level' => $level,
            'search' => $search,
            'levels' => $this->knownLevels(),
            'entries' => $paginatedEntries,
            'totalEntries' => $entries->count(),
        ]);
    }

    /** @return array<int, string> log filenames in storage/logs, newest-modified first */
    private function availableFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $paths = glob($directory.'/*.log') ?: [];

        return collect($paths)
            ->sortByDesc(fn (string $path) => filemtime($path) ?: 0)
            ->map(fn (string $path) => basename($path))
            ->values()
            ->all();
    }

    /**
     * Split a Laravel log file into one entry per timestamped line, keeping any
     * following stack-trace lines attached to that entry. No further parsing of the
     * message/exception body is performed — the raw text is passed straight through.
     *
     * @return Collection<int, array{time: string, level: string, message: string}>
     */
    private function readEntries(string $path): Collection
    {
        if (! is_file($path)) {
            return collect();
        }

        $contents = $this->tail($path, self::MAX_BYTES);
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $entries = collect();
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(?<time>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s*(?<rest>.*)$/', $line, $m)) {
                if ($current !== null) {
                    $entries->push($current);
                }

                $level = 'INFO';
                if (preg_match('/^\S+\.(\w+):/', $m['rest'], $levelMatch)) {
                    $level = strtoupper($levelMatch[1]);
                }

                $current = [
                    'time' => $m['time'],
                    'level' => $level,
                    'message' => rtrim($m['rest']),
                ];
            } elseif ($current !== null && $line !== '') {
                $current['message'] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $entries->push($current);
        }

        // Newest first, matching every other reporting page in the superadmin console.
        return $entries->reverse()->values();
    }

    /** Read at most $maxBytes from the end of a (possibly large) file. */
    private function tail(string $path, int $maxBytes): string
    {
        $size = filesize($path) ?: 0;

        if ($size <= $maxBytes) {
            return file_get_contents($path) ?: '';
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return '';
        }

        fseek($handle, -$maxBytes, SEEK_END);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        // The read window likely starts mid-entry; drop that partial first line.
        $firstBreak = strpos($contents, "\n[");
        if ($firstBreak !== false) {
            $contents = substr($contents, $firstBreak + 1);
        }

        return $contents;
    }

    /** @return array<int, string> */
    private function knownLevels(): array
    {
        return ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
    }
}

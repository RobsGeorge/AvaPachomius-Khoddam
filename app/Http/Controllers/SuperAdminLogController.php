<?php

namespace App\Http\Controllers;

use App\Services\ServerLogReader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Read-only viewer over the server log files (storage/logs/*.log) so a superadmin
 * can read production errors and their timestamps from the console instead of SSH.
 */
class SuperAdminLogController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly ServerLogReader $reader) {}

    public function index(Request $request)
    {
        $files = $this->reader->availableFiles();

        $selectedFile = $this->stringQuery($request, 'file');
        if (! $this->reader->isReadableFile($selectedFile)) {
            $selectedFile = $files[0]['name'] ?? null;
        }

        $level = $this->stringQuery($request, 'level');
        $search = $this->stringQuery($request, 'q');

        // read() returns an empty result for an unknown name, which covers "no log files yet".
        $result = $this->reader->read($selectedFile ?? '', ['level' => $level, 'q' => $search]);

        $levelCounts = $result['level_counts'];
        // Keep a level carried over from another file selectable, so the dropdown
        // never claims "all levels" while the table is filtered to nothing.
        if ($level !== null && ! array_key_exists($level, $levelCounts)) {
            $levelCounts[$level] = 0;
        }

        return view('superadmin.logs.index', [
            'files' => array_map(function (array $file) {
                $file['size_label'] = ServerLogReader::humanSize($file['size']);

                return $file;
            }, $files),
            'selectedFile' => $selectedFile,
            'level' => $level,
            'search' => $search,
            'levels' => $this->reader->orderLevels($levelCounts),
            'levelCounts' => $levelCounts,
            'totalScanned' => $result['total_scanned'],
            'matchCount' => count($result['entries']),
            'isFiltered' => $level !== null || $search !== null,
            'truncated' => $result['truncated'],
            'tailLimitLabel' => ServerLogReader::humanSize(ServerLogReader::TAIL_BYTES),
            'entries' => $this->paginate($result['entries'], $request),
        ]);
    }

    private function stringQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param list<array<string, mixed>> $entries */
    private function paginate(array $entries, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) Paginator::resolveCurrentPage());
        $items = array_slice($entries, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $items = array_map(function (array $entry) {
            $entry['variant'] = $this->reader->levelVariant($entry['level']);

            return $entry;
        }, $items);

        return new LengthAwarePaginator($items, count($entries), self::PER_PAGE, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);
    }
}

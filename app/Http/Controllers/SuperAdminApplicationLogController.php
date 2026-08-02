<?php

namespace App\Http\Controllers;

use App\Services\ApplicationLogReaderService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SuperAdminApplicationLogController extends Controller
{
    public function index(Request $request, ApplicationLogReaderService $reader)
    {
        $availableFiles = $reader->discoverLogFiles();
        $selectedFile = (string) $request->query('file', $reader->defaultBasename());

        if (! array_key_exists($selectedFile, $availableFiles)) {
            $selectedFile = $reader->defaultBasename();
        }

        $perPage = (int) $request->query('lines', 200);
        $level = $request->query('level');
        $search = $request->query('q');
        $page = (int) $request->query('page', 1);

        $result = $reader->tail(
            $selectedFile,
            $perPage,
            is_string($level) ? $level : null,
            is_string($search) ? trim($search) : null,
            $page
        );

        $entries = new LengthAwarePaginator(
            $result['entries'],
            $result['total'],
            $result['per_page'],
            $result['page'],
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('superadmin.logs.index', [
            'availableFiles' => $availableFiles,
            'selectedFile' => $selectedFile,
            'entries' => $entries,
            'missingFile' => $result['missing'],
            'level' => is_string($level) ? $level : '',
            'search' => is_string($search) ? trim($search) : '',
            'lines' => $result['per_page'],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ApplicationLogReaderService;
use Illuminate\Http\Request;

class SuperAdminApplicationLogController extends Controller
{
    public function index(Request $request, ApplicationLogReaderService $reader)
    {
        $availableFiles = $reader->discoverLogFiles();
        $selectedFile = (string) $request->query('file', $reader->defaultBasename());

        if (! array_key_exists($selectedFile, $availableFiles)) {
            $selectedFile = $reader->defaultBasename();
        }

        $maxLines = (int) $request->query('lines', 200);
        $level = $request->query('level');

        $result = $reader->tail($selectedFile, $maxLines, is_string($level) ? $level : null);

        return view('superadmin.logs.index', [
            'availableFiles' => $availableFiles,
            'selectedFile' => $selectedFile,
            'entries' => $result['entries'],
            'missingFile' => $result['missing'],
            'level' => is_string($level) ? $level : '',
            'lines' => max(10, min(500, $maxLines)),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ServerLogReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminServerLogController extends Controller
{
    public function index(Request $request, ServerLogReader $reader): View
    {
        $levelFilter = (string) $request->query('level', 'errors');
        $levels = $levelFilter === 'all' ? null : ServerLogReader::ERROR_LEVELS;

        $entries = $reader->recent(
            limit: (int) $request->query('limit', ServerLogReader::DEFAULT_LIMIT),
            levels: $levels,
        );

        return view('superadmin.server-logs.index', [
            'entries' => $entries,
            'levelFilter' => in_array($levelFilter, ['errors', 'all'], true) ? $levelFilter : 'errors',
            'logFiles' => array_map('basename', $reader->logFiles()),
        ]);
    }
}

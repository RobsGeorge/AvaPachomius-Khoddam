<?php

namespace App\Http\Controllers;

use App\Observability\ObservabilityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientErrorController extends Controller
{
    public function store(Request $request, ObservabilityRecorder $recorder): JsonResponse
    {
        if (! config('observability.client_beacon.enabled', true)) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $max = (int) config('observability.client_beacon.max_message_length', 2000);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.$max],
            'source' => ['nullable', 'string', 'max:500'],
            'lineno' => ['nullable', 'integer', 'min:0'],
            'colno' => ['nullable', 'integer', 'min:0'],
            'stack' => ['nullable', 'string', 'max:4000'],
            'type' => ['nullable', 'string', 'max:64'],
            'url' => ['nullable', 'string', 'max:2000'],
        ]);

        $recorder->record('frontend', 'error', $data['message'], [
            'source' => $data['source'] ?? null,
            'lineno' => $data['lineno'] ?? null,
            'colno' => $data['colno'] ?? null,
            'stack_excerpt' => isset($data['stack']) ? Str::limit($data['stack'], 4000, '') : null,
            'type' => $data['type'] ?? 'window.onerror',
            'url' => $data['url'] ?? $request->headers->get('referer'),
            'http_status' => null,
        ]);

        return response()->json(['ok' => true]);
    }
}

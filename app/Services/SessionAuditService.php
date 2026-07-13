<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SessionAuditService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(
        string $action,
        string $status,
        array $context = [],
        ?User $actor = null,
        ?Request $request = null,
    ): void {
        $actor ??= Auth::user();
        $request ??= request();

        try {
            ActivityLog::create([
                'user_id' => $actor?->user_id,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 1000, '') : null,
                'device_summary' => $request ? AuditLogService::deviceSummary($request) : null,
                'http_method' => $request?->method() ?? 'SYSTEM',
                'route_name' => 'sessions.action.'.$action,
                'url' => $request ? Str::limit($request->fullUrl(), 2000, '') : 'sessions://'.$action,
                'request_input' => array_merge([
                    'module' => 'sessions',
                    'action' => $action,
                    'status' => $status,
                ], $context),
                'response_status' => $status === 'success' ? 200 : 422,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Session audit log failed', ['error' => $e->getMessage(), 'action' => $action]);
        }
    }
}

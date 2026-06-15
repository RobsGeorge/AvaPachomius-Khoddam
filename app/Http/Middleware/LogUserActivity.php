<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        AuditLogService::capturePasswordFields($request);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        AuditLogService::logCapturedPasswordTrial($request, $response->getStatusCode());
        AuditLogService::logActivity($request, $response->getStatusCode());
    }
}

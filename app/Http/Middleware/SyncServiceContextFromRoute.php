<?php

namespace App\Http\Middleware;

use App\Services\ServiceContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncServiceContextFromRoute
{
    public function __construct(
        private ServiceContextService $serviceContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $service = $request->route()?->parameter('service');

        if ($user && $service) {
            $this->serviceContext->syncFromRoute($user, $service);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\ServiceContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureServiceContext
{
    /** @var list<string> */
    private array $exceptRouteNames = [
        'services.select',
        'services.select.store',
        'services.select.clear',
        'logout',
        'home',
        'locale.switch',
        'theme.update',
        'profile',
        // courses.select* intentionally NOT excepted: service must be chosen before course.
    ];

    /** @var list<string> */
    private array $exceptRoutePrefixes = [
        'admin.',
        'superadmin.',
        'roles.',
        'hubs.',
        'available-courses.',
        'events.',
        'services.apply',
        'services.application.',
    ];

    public function __construct(
        private ServiceContextService $serviceContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->serviceContext->requiresServiceContext($user)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName && $this->isExceptedRoute($routeName)) {
            return $next($request);
        }

        $this->serviceContext->autoSelectSingleService($user);

        if ($this->serviceContext->currentService($user)) {
            return $next($request);
        }

        if ($this->serviceContext->selectableServices($user)->isEmpty()) {
            return $next($request);
        }

        return redirect()->route('services.select', [
            'intended' => $request->fullUrl(),
        ]);
    }

    private function isExceptedRoute(string $routeName): bool
    {
        if (in_array($routeName, $this->exceptRouteNames, true)) {
            return true;
        }

        foreach ($this->exceptRoutePrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

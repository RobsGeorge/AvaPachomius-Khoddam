<?php

namespace App\Http\Middleware;

use App\Models\ChurchService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * T8b — permanent redirect from legacy numeric /services/{id}/… to slug URLs.
 * Skips API / JSON and routes that explicitly constrain {service} to digits.
 */
class RedirectNumericServiceToSlug
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return $next($request);
        }

        $route = $request->route();
        if (! $route || ! $route->hasParameter('service')) {
            return $next($request);
        }

        $wheres = $route->wheres ?? [];
        if (isset($wheres['service']) && preg_match('/^\[0-9/', (string) $wheres['service'])) {
            return $next($request);
        }

        $original = $route->originalParameter('service');
        if ($original === null || ! is_numeric($original) || ! ctype_digit((string) $original)) {
            return $next($request);
        }

        $service = $route->parameter('service');
        if (! $service instanceof ChurchService || blank($service->slug)) {
            return $next($request);
        }

        $name = $route->getName();
        if (! is_string($name) || $name === '') {
            return $next($request);
        }

        $parameters = $route->parameters();
        $parameters['service'] = $service;
        $target = route($name, $parameters, absolute: false);
        $query = $request->getQueryString();

        return redirect()->to($query ? $target.'?'.$query : $target, 301);
    }
}

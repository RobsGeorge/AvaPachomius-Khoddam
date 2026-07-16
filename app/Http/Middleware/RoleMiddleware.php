<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated Use permission: middleware. Role-name gates are forbidden (CLAUDE.md rule 4).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        abort(500, 'RoleMiddleware is retired — use permission: middleware.');
    }
}

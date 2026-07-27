<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prevent browsers from restoring stale HTML (with old CSRF tokens) via bfcache
 * after session expiry, force-logout, or 500 errors. Applies to guests and
 * authenticated users across the whole web portal.
 */
class PreventSessionPageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldPreventCaching($response)) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    protected function shouldPreventCaching(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($contentType === '') {
            return true;
        }

        if (str_contains($contentType, 'text/html')) {
            return true;
        }

        // Redirects often omit a useful content-type; still avoid caching them.
        if ($response->isRedirection()) {
            return true;
        }

        return false;
    }
}

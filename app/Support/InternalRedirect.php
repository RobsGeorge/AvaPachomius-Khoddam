<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Convert an action URL into a same-app path so notification/announcement
 * clicks never leave this host (APP_URL mismatches otherwise hang the browser).
 */
final class InternalRedirect
{
    public static function path(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (Str::startsWith($url, '//')) {
            return null;
        }

        if (Str::startsWith($url, '/')) {
            return $url;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        if ($host === null || $host === '') {
            return null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $requestHost = request()->getHost();

        if ($host !== $requestHost && ($appHost === null || $host !== $appHost)) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?'.$parts['query'];
        }

        return $path;
    }
}

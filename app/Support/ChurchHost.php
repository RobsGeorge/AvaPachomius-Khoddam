<?php

namespace App\Support;

use App\Models\Church;
use Illuminate\Support\Str;

/**
 * Build absolute URLs for a church host (custom domain or {slug}.{base}).
 * Church switching is host-based — never a session POST.
 */
final class ChurchHost
{
    public static function url(Church $church, string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/') {
            $path = '/';
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ':'.$port : '';

        $host = self::hostFor($church);

        return $scheme.'://'.$host.$portSuffix.$path;
    }

    public static function hostFor(Church $church): string
    {
        if (filled($church->domain)) {
            return (string) $church->domain;
        }

        $base = self::baseHost();

        return $church->slug.'.'.$base;
    }

    public static function baseHost(): string
    {
        $configured = config('tenancy.base_domain');
        if (filled($configured)) {
            return ltrim((string) $configured, '.');
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        // If APP_URL is already a church subdomain in local dev, peel one label.
        $console = (string) config('tenancy.console_host');
        if ($host === $console) {
            $parts = explode('.', $host);
            array_shift($parts);

            return implode('.', $parts) ?: 'localhost';
        }

        return $host;
    }

    public static function isConsoleHost(?string $host = null): bool
    {
        $host ??= request()->getHost();

        return $host === config('tenancy.console_host');
    }

    public static function consoleUrl(string $path = '/'): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ':'.$port : '';
        $path = '/'.ltrim($path, '/');

        return $scheme.'://'.config('tenancy.console_host').$portSuffix.($path === '/' ? '/' : $path);
    }

    public static function pathPreservingUrl(Church $church): string
    {
        $path = request()->getRequestUri() ?: '/';

        // Avoid carrying console-only paths onto a church host.
        if (Str::startsWith($path, '/superadmin/churches')) {
            $path = '/dashboard';
        }

        return self::url($church, $path);
    }
}

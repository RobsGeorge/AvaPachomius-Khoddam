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

    /**
     * Effective superadmin console host.
     *
     * Prefer TENANCY_CONSOLE_HOST when it is a real (non-local) host. If staging/prod
     * still has the .env.example leftover `admin.localhost` while TENANCY_BASE_DOMAIN
     * (or APP_URL) is public, derive `admin.{base}` so nav links and ResolveTenant
     * stay on the live console host.
     */
    public static function consoleHost(): string
    {
        $configured = trim((string) config('tenancy.console_host'));
        $base = self::baseHost();

        if ($configured !== '' && ! self::isLocalDevHost($configured)) {
            return $configured;
        }

        if ($base !== '' && ! self::isLocalDevHost($base)) {
            return 'admin.'.$base;
        }

        return $configured !== '' ? $configured : 'admin.localhost';
    }

    public static function isConsoleHost(?string $host = null): bool
    {
        $host ??= request()->getHost();

        return strcasecmp((string) $host, self::consoleHost()) === 0;
    }

    public static function consoleUrl(string $path = '/'): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ':'.$port : '';
        $path = '/'.ltrim($path, '/');

        return $scheme.'://'.self::consoleHost().$portSuffix.($path === '/' ? '/' : $path);
    }

    private static function isLocalDevHost(string $host): bool
    {
        $host = strtolower(trim($host));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || Str::endsWith($host, '.localhost')
            || Str::endsWith($host, '.local');
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

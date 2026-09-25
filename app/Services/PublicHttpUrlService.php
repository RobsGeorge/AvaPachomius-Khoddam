<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Best-effort check that a student-submitted link is an http(s) URL and
 * reachable without auth. Private/loopback hosts are rejected (SSRF).
 */
class PublicHttpUrlService
{
    public function assertPublicHttpUrl(string $url, string $field = 'link_url'): void
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                $field => [__('projects.submission_link_invalid')],
            ]);
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                $field => [__('projects.submission_link_http_only')],
            ]);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $this->isBlockedHost($host)) {
            throw ValidationException::withMessages([
                $field => [__('projects.submission_link_not_public')],
            ]);
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'Khedma-LinkCheck/1.0'])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->head($url);

            if (! $response->successful()) {
                $response = Http::timeout(8)
                    ->connectTimeout(5)
                    ->withHeaders(['User-Agent' => 'Khedma-LinkCheck/1.0'])
                    ->withOptions(['allow_redirects' => ['max' => 5]])
                    ->get($url);
            }
        } catch (Throwable) {
            throw ValidationException::withMessages([
                $field => [__('projects.submission_link_unreachable')],
            ]);
        }

        if ($response->unauthorized() || $response->forbidden()) {
            throw ValidationException::withMessages([
                $field => [__('projects.submission_link_not_public')],
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                $field => [__('projects.submission_link_unreachable')],
            ]);
        }
    }

    private function isBlockedHost(string $host): bool
    {
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return true;
        }

        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}

<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Unrecoverable storage/cache permission failure after soft-chmod / recreate attempts.
 * Rendered as a user-facing 503 (toast redirect or dedicated page) — never a bare 500.
 */
class StoragePermissionException extends Exception
{
    public static function fromThrowable(Throwable $e): self
    {
        if ($e instanceof self) {
            return $e;
        }

        return new self(
            $e->getMessage() !== '' ? $e->getMessage() : 'Storage permission failure',
            (int) $e->getCode(),
            $e
        );
    }

    public function render(Request $request): SymfonyResponse
    {
        $message = __('app.storage_unavailable');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'storage_unavailable',
            ], 503);
        }

        $redirect = $this->tryFlashRedirect($request, $message);
        if ($redirect) {
            return $redirect;
        }

        return response()->view('errors.storage-unavailable', [
            'message' => $message,
            'title' => __('app.storage_unavailable_title'),
            'contact' => __('app.storage_unavailable_contact'),
        ], 503);
    }

    /**
     * A persistently broken session/cache file (e.g. wrong owner/mode for one
     * visitor after a deploy chmod/chown pass) fails identically on *every*
     * request, not just this one. Bouncing to "referer" or "previous URL" then
     * risks an infinite redirect: request A fails and redirects to B, B fails the
     * same way and redirects back to A (or to the login/profile fallback, which
     * itself fails and redirects to B again) — forever, i.e. the browser's
     * "too many redirects" error. `RETRY_MARKER` caps this at a single extra hop:
     * once a redirect we issued for this exact failure is followed and fails
     * again, we stop trying to be clever and show the dedicated error page.
     */
    private const RETRY_MARKER = 'storage_retry';

    private function tryFlashRedirect(Request $request, string $message): ?RedirectResponse
    {
        if ($request->query(self::RETRY_MARKER) === '1') {
            return null;
        }

        try {
            if (! $request->hasSession()) {
                return null;
            }

            $fallback = $request->user()
                ? route('profile')
                : route('login');

            $referer = $request->headers->get('referer');
            $previous = url()->previous();
            $target = is_string($referer) && $referer !== '' ? $referer : $previous;

            if (! is_string($target) || $target === '') {
                $target = $fallback;
            }

            // Belt-and-suspenders: never hand back the exact URL or same path that just failed.
            if ($target === $request->fullUrl() || $this->sameRequestPath($request, $target)) {
                return null;
            }

            return redirect()
                ->to($this->withRetryMarker($target))
                ->with('error', $message);
        } catch (Throwable) {
            return null;
        }
    }

    private function sameRequestPath(Request $request, string $target): bool
    {
        $currentPath = '/'.ltrim($request->path(), '/');
        $targetPath = parse_url($target, PHP_URL_PATH);

        if (! is_string($targetPath) || $targetPath === '') {
            if (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
                $targetPath = $target;
            } else {
                return false;
            }
        }

        $targetPath = '/'.ltrim($targetPath, '/');

        return rtrim($currentPath, '/') === rtrim($targetPath, '/');
    }

    private function withRetryMarker(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').self::RETRY_MARKER.'=1';
    }
}

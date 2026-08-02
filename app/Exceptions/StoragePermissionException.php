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

    private function tryFlashRedirect(Request $request, string $message): ?RedirectResponse
    {
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

            // Never bounce to the same path — that becomes an infinite redirect loop
            // (common when session files are deploy-owned after a VPS permission change).
            if ($this->sameRequestPath($request, $target) || $target === $request->fullUrl()) {
                return null;
            }

            return redirect()
                ->to($target)
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
}

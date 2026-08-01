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

            $target = url()->previous();
            if (! is_string($target) || $target === '' || $target === $request->fullUrl()) {
                $target = $fallback;
            }

            return redirect()
                ->to($target)
                ->with('error', $message);
        } catch (Throwable) {
            return null;
        }
    }
}

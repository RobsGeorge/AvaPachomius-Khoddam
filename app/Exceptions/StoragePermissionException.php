<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Unrecoverable storage/cache permission failure after soft-chmod / recreate attempts.
 * Rendered as a user-facing 503 dedicated page — never a bare 500.
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

    /**
     * The response must be terminal. Redirecting sends the browser to another page
     * served by the same broken storage, and a guest bounces between `/` and `/login`
     * until the browser gives up with ERR_TOO_MANY_REDIRECTS.
     */
    public function render(Request $request): SymfonyResponse
    {
        $message = __('app.storage_unavailable');

        $headers = [
            'Retry-After' => '60',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma' => 'no-cache',
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'storage_unavailable',
            ], 503, $headers);
        }

        return response()->view('errors.storage-unavailable', [
            'message' => $message,
            'title' => __('app.storage_unavailable_title'),
            'contact' => __('app.storage_unavailable_contact'),
        ], 503, $headers);
    }
}

<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * After session flush / force-logout, open tabs still hold stale CSRF tokens.
     * Redirect to a fresh GET instead of the bare "Page Expired" 419 screen.
     *
     * Handled here (before parent::prepareException) because Laravel converts
     * TokenMismatchException into HttpException(419), which bypasses renderable
     * callbacks typed for TokenMismatchException.
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof TokenMismatchException) {
            return $this->redirectAfterTokenMismatch($request);
        }

        $response = parent::render($request, $e);

        if ($response->getStatusCode() >= 400) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function redirectAfterTokenMismatch(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('auth.page_expired')], 419);
        }

        $fallback = Auth::check() ? route('profile') : route('login');
        $target = $this->safeSameHostUrl(
            $request->headers->get('referer') ?: url()->previous(),
            $request->fullUrl(),
            $fallback
        );

        return redirect()
            ->to($target)
            ->withInput($request->except('_token', 'password', 'password_confirmation', 'profile_photo'))
            ->with('warning', __('auth.page_expired'));
    }

    private function safeSameHostUrl(?string $candidate, string $currentUrl, string $fallback): string
    {
        if (! $candidate || $candidate === $currentUrl) {
            return $fallback;
        }

        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')) {
            return $candidate;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $candidateHost = parse_url($candidate, PHP_URL_HOST);

        if (! $candidateHost || ! $appHost || strcasecmp((string) $candidateHost, (string) $appHost) !== 0) {
            return $fallback;
        }

        return $candidate;
    }
}

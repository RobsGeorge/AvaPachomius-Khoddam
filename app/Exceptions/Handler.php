<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
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
     * Prevent browsers / intermediaries from caching error pages so a one-off
     * 500 does not appear to "stick" on refresh / back navigation.
     */
    public function render($request, Throwable $e): Response
    {
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
}

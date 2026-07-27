<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class CsrfTokenController extends Controller
{
    /**
     * Return the current session CSRF token so idle/bfcache tabs can heal.
     * Guest-safe: relies only on the session cookie (same as any HTML page).
     */
    public function show(): JsonResponse
    {
        return response()
            ->json(['token' => csrf_token()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
    }
}

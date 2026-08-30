<?php

namespace App\Http\Middleware;

use App\Http\Controllers\LocaleController;
use App\Services\TranslationRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private TranslationRepository $translations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('translation.supported_locales', ['ar', 'en']);
        $fallback = config('app.locale', 'ar');

        $candidates = [
            $request->session()->get('locale'),
        ];

        if ($request->user() && Schema::hasColumn('user', 'ui_locale')) {
            $candidates[] = $request->user()->ui_locale;
        }

        $candidates[] = $request->cookie(LocaleController::COOKIE);
        $candidates[] = $fallback;

        $locale = $fallback;
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                $locale = $candidate;
                break;
            }
        }

        App::setLocale($locale);
        $this->translations->mergeDatabaseLines($locale);

        return $next($request);
    }
}

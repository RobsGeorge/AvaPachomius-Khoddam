<?php

namespace App\Http\Middleware;

use App\Services\TranslationRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private TranslationRepository $translations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale', 'ar'));

        if (! in_array($locale, config('translation.supported_locales', ['ar', 'en']), true)) {
            $locale = 'ar';
        }

        App::setLocale($locale);
        $this->translations->mergeDatabaseLines($locale);

        return $next($request);
    }
}

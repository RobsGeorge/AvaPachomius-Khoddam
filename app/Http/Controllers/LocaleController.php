<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LocaleController extends Controller
{
    public const COOKIE = 'ui_locale';

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public function switch(Request $request, string $locale)
    {
        if (! in_array($locale, config('translation.supported_locales', ['ar', 'en']), true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        if ($request->user() && Schema::hasColumn('user', 'ui_locale')) {
            $request->user()->forceFill(['ui_locale' => $locale])->save();
        }

        return redirect()
            ->back()
            ->cookie(
                self::COOKIE,
                $locale,
                self::COOKIE_MINUTES,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            );
    }
}

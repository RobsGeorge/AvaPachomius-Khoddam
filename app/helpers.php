<?php

use Illuminate\Support\Facades\App;

if (! function_exists('locale_dir')) {
    function locale_dir(?string $locale = null): string
    {
        $locale ??= App::getLocale();

        return in_array($locale, config('translation.rtl_locales', ['ar']), true) ? 'rtl' : 'ltr';
    }
}

if (! function_exists('is_rtl')) {
    function is_rtl(?string $locale = null): bool
    {
        return locale_dir($locale) === 'rtl';
    }
}

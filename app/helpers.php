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

if (! function_exists('current_course')) {
    function current_course(): ?\App\Models\Course
    {
        $route = request()->route();
        if ($route) {
            $course = $route->parameter('course');
            if ($course instanceof \App\Models\Course) {
                return $course;
            }

            if (is_string($course) && $course !== '') {
                $fromRoute = \App\Models\Course::find($course);
                if ($fromRoute) {
                    return $fromRoute;
                }
            }
        }

        return app(\App\Services\CourseContextService::class)->currentCourse();
    }
}

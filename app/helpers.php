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
        if (! $route) {
            return null;
        }

        $course = $route->parameter('course');
        if ($course instanceof \App\Models\Course) {
            return $course;
        }

        if (is_string($course) && $course !== '') {
            return \App\Models\Course::find($course);
        }

        $sessionId = session('current_course_id');
        if ($sessionId) {
            return \App\Models\Course::find($sessionId);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user) {
            $first = $user->userCourseRoles()->whereNull('staff_archived_at')->first();

            return $first ? \App\Models\Course::find($first->course_id) : null;
        }

        return null;
    }
}

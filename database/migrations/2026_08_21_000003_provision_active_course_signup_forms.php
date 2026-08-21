<?php

use App\Services\CourseApplicationFormService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Registration enrollment listed only courses with an already-enabled
 * application form. Existing production courses never had that form, so
 * new signups saw "no courses eligible". Provision a default enabled
 * form (student role + profile fields) for every currently-active course.
 *
 * Additive: creates/updates form rows only. Closed/archived courses are
 * skipped by Course::isActive().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course') || ! Schema::hasTable('course_application_forms')) {
            return;
        }

        app(CourseApplicationFormService::class)->provisionActiveCoursesForPublicSignup();
    }

    public function down(): void
    {
        // Expand-only: keep provisioned forms and enabled flags.
    }
};

<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T3-expand — anchor the (already dynamic) RBAC to churches. Additive only, dormant:
 * a nullable, indexed church_id on `roles` and `user_course_role` (null = platform
 * template / not-yet-scoped), plus church.permissions_version for cache busting once
 * the church-contextual resolver lands (T3-enforce). Nothing reads these yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('roles', 'church_id', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->nullable()->index();
        });

        MigrationSupport::addColumn('user_course_role', 'church_id', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->nullable()->index();
        });

        MigrationSupport::addColumn('church', 'permissions_version', function (Blueprint $table) {
            $table->unsignedInteger('permissions_version')->default(1);
        });
    }

    public function down(): void
    {
        foreach ([['roles', 'church_id'], ['user_course_role', 'church_id'], ['church', 'permissions_version']] as [$t, $c]) {
            if (Schema::hasColumn($t, $c)) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn($c));
            }
        }
    }
};

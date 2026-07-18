<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id('permission_group_id');
            $table->string('group_key', 64)->unique();
            $table->string('label_en', 120);
            $table->string('label_ar', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('scope', 16)->default('course'); // system, course, both
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id('permission_id');
            $table->foreignId('permission_group_id')->constrained('permission_groups', 'permission_group_id')->cascadeOnDelete();
            $table->string('key', 80)->unique();
            $table->string('type', 16)->default('both'); // endpoint, ui_entry, both
            $table->string('label_en', 120);
            $table->string('label_ar', 120)->nullable();
            $table->text('description')->nullable();
            $table->json('route_names')->nullable();
            $table->json('http_methods')->nullable();
            $table->string('nav_key', 80)->nullable();
            $table->boolean('is_system_only')->default(false);
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamps();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->after('role_id');
            $table->string('slug', 64)->nullable()->after('role_name');
            $table->text('description')->nullable()->after('role_decription');
            $table->boolean('is_system')->default(false)->after('description');
            $table->boolean('is_template')->default(false)->after('is_system');
            $table->unsignedBigInteger('cloned_from_role_id')->nullable()->after('is_template');
            $table->unsignedInteger('permissions_version')->default(0)->after('cloned_from_role_id');

            $table->foreign('course_id')->references('course_id')->on('course')->nullOnDelete();
            $table->foreign('cloned_from_role_id')->references('role_id')->on('roles')->nullOnDelete();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('permission_id')->on('permissions')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('course_admin_group_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_group_id')->constrained('permission_groups', 'permission_group_id')->cascadeOnDelete();
            $table->boolean('visible_to_course_admins')->default(true);
            $table->unsignedBigInteger('set_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('set_by_user_id')->references('user_id')->on('user')->nullOnDelete();
            $table->unique('permission_group_id');
        });

        Schema::create('user_system_role', function (Blueprint $table) {
            $table->id('user_system_role_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::table('course', function (Blueprint $table) {
            $table->unsignedInteger('permissions_version')->default(0)->after('grace_eligibility_mode');
            $table->unsignedBigInteger('roles_cloned_from_course_id')->nullable()->after('permissions_version');

            $table->foreign('roles_cloned_from_course_id')->references('course_id')->on('course')->nullOnDelete();
        });

        Schema::table('user_course_role', function (Blueprint $table) {
            // Keep one assignment per user+course (latest row wins).
        });

        $duplicates = DB::table('user_course_role')
            ->select('user_id', 'course_id', DB::raw('MAX(user_course_role_id) as keep_id'))
            ->groupBy('user_id', 'course_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('user_course_role')
                ->where('user_id', $dup->user_id)
                ->where('course_id', $dup->course_id)
                ->where('user_course_role_id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('user_course_role', function (Blueprint $table) {
            $table->unique(['user_id', 'course_id'], 'user_course_role_user_course_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_course_role', function (Blueprint $table) {
            $table->dropUnique('user_course_role_user_course_unique');
        });

        Schema::table('course', function (Blueprint $table) {
            $table->dropForeign(['roles_cloned_from_course_id']);
            $table->dropColumn(['permissions_version', 'roles_cloned_from_course_id']);
        });

        Schema::dropIfExists('user_system_role');
        Schema::dropIfExists('course_admin_group_visibility');
        Schema::dropIfExists('role_permission');
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['cloned_from_role_id']);
            $table->dropColumn([
                'course_id', 'slug', 'description', 'is_system',
                'is_template', 'cloned_from_role_id', 'permissions_version',
            ]);
        });
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permission_groups');
    }
};

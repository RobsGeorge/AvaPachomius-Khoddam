<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service')) {
            Schema::create('service', function (Blueprint $table) {
                $table->id('service_id');
                $table->string('title', 191);
                $table->string('title_ar', 191)->nullable();
                $table->string('title_en', 191)->nullable();
                $table->text('description')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->json('branding_theme')->nullable();
                $table->string('status', 32)->default('active');
                $table->unsignedInteger('permissions_version')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('course') && ! Schema::hasColumn('course', 'service_id')) {
            Schema::table('course', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('course_id');
                $table->foreign('service_id')->references('service_id')->on('service')->nullOnDelete();
            });
        }

        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'service_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('course_id');
                $table->foreign('service_id')->references('service_id')->on('service')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('user_service_role')) {
            Schema::create('user_service_role', function (Blueprint $table) {
                $table->id('user_service_role_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('role_id');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
                $table->foreign('service_id')->references('service_id')->on('service')->cascadeOnDelete();
                $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();
                $table->unique(['user_id', 'service_id']);
            });
        }

        $this->backfillDefaultService();
    }

    public function down(): void
    {
        // Expand-only: no destructive down in production. Keep reverse for tests.
        Schema::dropIfExists('user_service_role');

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'service_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            });
        }

        if (Schema::hasTable('course') && Schema::hasColumn('course', 'service_id')) {
            Schema::table('course', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            });
        }

        Schema::dropIfExists('service');
    }

    private function backfillDefaultService(): void
    {
        if (! Schema::hasTable('service')) {
            return;
        }

        $defaultId = DB::table('service')->orderBy('service_id')->value('service_id');

        if (! $defaultId) {
            $defaultId = DB::table('service')->insertGetId([
                'title' => 'Default Service',
                'title_ar' => 'الخدمة الاساسية',
                'title_en' => 'Default Service',
                'description' => 'Backfilled parent for existing courses.',
                'status' => 'active',
                'permissions_version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'service_id');
        }

        if (Schema::hasTable('course') && Schema::hasColumn('course', 'service_id')) {
            DB::table('course')->whereNull('service_id')->update(['service_id' => $defaultId]);
        }

        $memberRoleId = null;
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'service_id')) {
            $memberRoleId = DB::table('roles')
                ->where('service_id', $defaultId)
                ->whereNull('course_id')
                ->where('is_template', false)
                ->where('slug', 'service-member')
                ->value('role_id');

            if (! $memberRoleId) {
                $memberRoleId = DB::table('roles')->insertGetId([
                    'role_name' => 'Service Member',
                    'role_decription' => 'Service membership',
                    'slug' => 'service-member',
                    'course_id' => null,
                    'service_id' => $defaultId,
                    'is_system' => false,
                    'is_template' => false,
                    'permissions_version' => 0,
                ], 'role_id');
            }
        }

        if (! $memberRoleId || ! Schema::hasTable('user_service_role') || ! Schema::hasTable('user_course_role')) {
            return;
        }

        $userIds = DB::table('user_course_role')->distinct()->pluck('user_id');
        foreach ($userIds as $userId) {
            $exists = DB::table('user_service_role')
                ->where('user_id', $userId)
                ->where('service_id', $defaultId)
                ->exists();

            if ($exists) {
                continue;
            }

            $hasPrimary = DB::table('user_service_role')
                ->where('user_id', $userId)
                ->where('is_primary', true)
                ->exists();

            DB::table('user_service_role')->insert([
                'user_id' => $userId,
                'service_id' => $defaultId,
                'role_id' => $memberRoleId,
                'is_primary' => ! $hasPrimary,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};

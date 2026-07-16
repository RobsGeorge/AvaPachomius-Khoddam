<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permission_groups')) {
            Schema::create('permission_groups', function (Blueprint $table) {
                $table->id('permission_group_id');
                $table->string('group_key', 64)->unique();
                $table->string('label_en', 120);
                $table->string('label_ar', 120)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('scope', 16)->default('course'); // system, course, both
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
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
        }

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (! Schema::hasColumn('roles', 'course_id')) {
                    $col = $table->unsignedBigInteger('course_id')->nullable();
                    if (Schema::hasColumn('roles', 'role_id')) {
                        $col->after('role_id');
                    }
                }
                if (! Schema::hasColumn('roles', 'slug')) {
                    $col = $table->string('slug', 64)->nullable();
                    if (Schema::hasColumn('roles', 'role_name')) {
                        $col->after('role_name');
                    }
                }
                if (! Schema::hasColumn('roles', 'description')) {
                    $col = $table->text('description')->nullable();
                    if (Schema::hasColumn('roles', 'role_decription')) {
                        $col->after('role_decription');
                    }
                }
                if (! Schema::hasColumn('roles', 'is_system')) {
                    $table->boolean('is_system')->default(false);
                }
                if (! Schema::hasColumn('roles', 'is_template')) {
                    $table->boolean('is_template')->default(false);
                }
                if (! Schema::hasColumn('roles', 'cloned_from_role_id')) {
                    $table->unsignedBigInteger('cloned_from_role_id')->nullable();
                }
                if (! Schema::hasColumn('roles', 'permissions_version')) {
                    $table->unsignedInteger('permissions_version')->default(0);
                }
            });

            Schema::table('roles', function (Blueprint $table) {
                if (Schema::hasColumn('roles', 'course_id') && Schema::hasTable('course')) {
                    try {
                        $table->foreign('course_id')->references('course_id')->on('course')->nullOnDelete();
                    } catch (\Throwable) {
                        // FK may already exist from a prior expand.
                    }
                }
                if (Schema::hasColumn('roles', 'cloned_from_role_id')) {
                    try {
                        $table->foreign('cloned_from_role_id')->references('role_id')->on('roles')->nullOnDelete();
                    } catch (\Throwable) {
                        // FK may already exist from a prior expand.
                    }
                }
            });
        }

        if (! Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');
                $table->timestamps();

                $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();
                $table->foreign('permission_id')->references('permission_id')->on('permissions')->cascadeOnDelete();
                $table->unique(['role_id', 'permission_id']);
            });
        }

        if (! Schema::hasTable('course_admin_group_visibility')) {
            Schema::create('course_admin_group_visibility', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permission_group_id')->constrained('permission_groups', 'permission_group_id')->cascadeOnDelete();
                $table->boolean('visible_to_course_admins')->default(true);
                $table->unsignedBigInteger('set_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('set_by_user_id')->references('user_id')->on('user')->nullOnDelete();
                $table->unique('permission_group_id');
            });
        }

        if (! Schema::hasTable('user_system_role')) {
            Schema::create('user_system_role', function (Blueprint $table) {
                $table->id('user_system_role_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('role_id');
                $table->timestamps();

                $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
                $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();
                $table->unique(['user_id', 'role_id']);
            });
        }

        if (Schema::hasTable('course')) {
            Schema::table('course', function (Blueprint $table) {
                if (! Schema::hasColumn('course', 'permissions_version')) {
                    $col = $table->unsignedInteger('permissions_version')->default(0);
                    if (Schema::hasColumn('course', 'grace_eligibility_mode')) {
                        $col->after('grace_eligibility_mode');
                    }
                }
                if (! Schema::hasColumn('course', 'roles_cloned_from_course_id')) {
                    $table->unsignedBigInteger('roles_cloned_from_course_id')->nullable();
                }
            });

            if (Schema::hasColumn('course', 'roles_cloned_from_course_id')) {
                Schema::table('course', function (Blueprint $table) {
                    try {
                        $table->foreign('roles_cloned_from_course_id')->references('course_id')->on('course')->nullOnDelete();
                    } catch (\Throwable) {
                        // FK may already exist.
                    }
                });
            }
        }

        if (Schema::hasTable('user_course_role')) {
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
                try {
                    $table->unique(['user_id', 'course_id'], 'user_course_role_user_course_unique');
                } catch (\Throwable) {
                    // Unique index may already exist.
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_course_role')) {
            Schema::table('user_course_role', function (Blueprint $table) {
                try {
                    $table->dropUnique('user_course_role_user_course_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('course')) {
            Schema::table('course', function (Blueprint $table) {
                try {
                    $table->dropForeign(['roles_cloned_from_course_id']);
                } catch (\Throwable) {
                }
                foreach (['permissions_version', 'roles_cloned_from_course_id'] as $column) {
                    if (Schema::hasColumn('course', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('user_system_role');
        Schema::dropIfExists('course_admin_group_visibility');
        Schema::dropIfExists('role_permission');

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                try {
                    $table->dropForeign(['course_id']);
                } catch (\Throwable) {
                }
                try {
                    $table->dropForeign(['cloned_from_role_id']);
                } catch (\Throwable) {
                }
                foreach ([
                    'course_id', 'slug', 'description', 'is_system',
                    'is_template', 'cloned_from_role_id', 'permissions_version',
                ] as $column) {
                    if (Schema::hasColumn('roles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permission_groups');
    }
};

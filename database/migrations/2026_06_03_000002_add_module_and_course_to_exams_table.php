<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exams')) {
            return;
        }

        MigrationSupport::addColumn('exams', 'course_id', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->after('exam_id');
        });

        MigrationSupport::addColumn('exams', 'module_id', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->after('course_id');
        });

        if (Schema::hasTable('course') && Schema::hasColumn('exams', 'course_id')
            && ! MigrationSupport::foreignKeyExists('exams', 'exams_course_id_foreign')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->foreign('course_id', 'exams_course_id_foreign')
                    ->references('course_id')
                    ->on('course')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('modules') && Schema::hasColumn('exams', 'module_id')
            && ! MigrationSupport::foreignKeyExists('exams', 'exams_module_id_foreign')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->foreign('module_id', 'exams_module_id_foreign')
                    ->references('module_id')
                    ->on('modules')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('exams')) {
            return;
        }

        if (MigrationSupport::foreignKeyExists('exams', 'exams_module_id_foreign')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropForeign('exams_module_id_foreign');
            });
        }

        if (MigrationSupport::foreignKeyExists('exams', 'exams_course_id_foreign')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropForeign('exams_course_id_foreign');
            });
        }

        foreach (['module_id', 'course_id'] as $column) {
            if (Schema::hasColumn('exams', $column)) {
                Schema::table('exams', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};

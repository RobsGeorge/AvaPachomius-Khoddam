<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('session') || ! Schema::hasTable('modules')) {
            return;
        }

        MigrationSupport::addColumn('session', 'module_id', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->after('course_id');
        });

        if (Schema::hasColumn('session', 'module_id')
            && ! MigrationSupport::foreignKeyExists('session', 'session_module_id_foreign')) {
            Schema::table('session', function (Blueprint $table) {
                $table->foreign('module_id', 'session_module_id_foreign')
                    ->references('module_id')
                    ->on('modules')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('session') || ! Schema::hasColumn('session', 'module_id')) {
            return;
        }

        if (MigrationSupport::foreignKeyExists('session', 'session_module_id_foreign')) {
            Schema::table('session', function (Blueprint $table) {
                $table->dropForeign('session_module_id_foreign');
            });
        }

        Schema::table('session', function (Blueprint $table) {
            $table->dropColumn('module_id');
        });
    }
};

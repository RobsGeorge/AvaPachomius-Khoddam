<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('exams', 'results_announced_at', function (Blueprint $table) {
            $table->timestamp('results_announced_at')->nullable()->after('allow_late_entry');
        });

        MigrationSupport::addColumn('exams', 'results_announced_by_user_id', function (Blueprint $table) {
            $after = Schema::hasColumn('exams', 'results_announced_at')
                ? 'results_announced_at'
                : 'allow_late_entry';
            $table->unsignedBigInteger('results_announced_by_user_id')->nullable()->after($after);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exams')) {
            return;
        }

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'results_announced_by_user_id')) {
                $table->dropColumn('results_announced_by_user_id');
            }
            if (Schema::hasColumn('exams', 'results_announced_at')) {
                $table->dropColumn('results_announced_at');
            }
        });
    }
};

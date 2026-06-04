<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course')) {
            return;
        }

        MigrationSupport::addColumn('course', 'passing_percentage', function (Blueprint $table) {
            $table->decimal('passing_percentage', 5, 2)->nullable()->after('year');
        });

        MigrationSupport::addColumn('course', 'min_attendance_percentage', function (Blueprint $table) {
            $table->decimal('min_attendance_percentage', 5, 2)->nullable()->after('passing_percentage');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course')) {
            return;
        }

        foreach (['min_attendance_percentage', 'passing_percentage'] as $column) {
            if (Schema::hasColumn('course', $column)) {
                Schema::table('course', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};

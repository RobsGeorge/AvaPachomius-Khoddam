<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['user', 'course', 'modules', 'content', 'assignments'] as $table) {
            if (Schema::hasTable($table)) {
                MigrationSupport::addBooleanColumn($table, 'is_demo', false);
            }
        }
    }

    public function down(): void
    {
        foreach (['user', 'course', 'modules', 'content', 'assignments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_demo')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('is_demo');
                });
            }
        }
    }
};

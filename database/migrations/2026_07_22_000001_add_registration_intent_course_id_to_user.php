<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user') || Schema::hasColumn('user', 'registration_intent_course_id')) {
            return;
        }

        Schema::table('user', function (Blueprint $table) {
            $table->unsignedBigInteger('registration_intent_course_id')->nullable()->after('application_status');
        });

        if (Schema::hasTable('course')) {
            Schema::table('user', function (Blueprint $table) {
                $table->foreign('registration_intent_course_id')
                    ->references('course_id')
                    ->on('course')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('user', 'registration_intent_course_id')) {
            return;
        }

        Schema::table('user', function (Blueprint $table) {
            $table->dropForeign(['registration_intent_course_id']);
            $table->dropColumn('registration_intent_course_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course', function (Blueprint $table) {
            $table->time('default_session_start_time')
                ->nullable()
                ->after('year');
        });
    }

    public function down(): void
    {
        Schema::table('course', function (Blueprint $table) {
            $table->dropColumn('default_session_start_time');
        });
    }
};

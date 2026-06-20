<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session', function (Blueprint $table) {
            $table->time('session_start_time')->nullable()->after('session_date');
        });
    }

    public function down(): void
    {
        Schema::table('session', function (Blueprint $table) {
            $table->dropColumn('session_start_time');
        });
    }
};

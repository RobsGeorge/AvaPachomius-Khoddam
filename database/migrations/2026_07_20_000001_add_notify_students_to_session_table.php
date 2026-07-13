<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session', function (Blueprint $table) {
            $table->boolean('notify_students')->default(true)->after('session_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('session', function (Blueprint $table) {
            $table->dropColumn('notify_students');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submission', function (Blueprint $table) {
            $table->dropColumn('team_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submission', function (Blueprint $table) {
            $table->unsignedBigInteger('team_submission_id')->nullable();
        });
    }
}; 
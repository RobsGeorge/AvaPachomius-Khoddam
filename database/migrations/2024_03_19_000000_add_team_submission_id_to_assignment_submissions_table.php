<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('assignment_submission', function (Blueprint $table) {
            $table->unsignedBigInteger('team_submission_id')->nullable();
            $table->foreign('team_submission_id')
                  ->references('submission_id')
                  ->on('assignment_submission')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('assignment_submission', function (Blueprint $table) {
            $table->dropForeign(['team_submission_id']);
            $table->dropColumn('team_submission_id');
        });
    }
}; 
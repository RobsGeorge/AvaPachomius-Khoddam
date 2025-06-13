<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->string('assignment_name');
            $table->text('assignment_description');
            $table->integer('total_points');
            $table->dateTime('due_date');
            $table->text('instructions')->nullable();
            $table->text('resources')->nullable();
            $table->timestamps();
        });

        Schema::create('assignment_submission', function (Blueprint $table) {
            $table->id('submission_id');
            $table->foreignId('assignment_id')->constrained('assignments', 'assignment_id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('user', 'id')->onDelete('cascade');
            $table->text('submission_content');
            $table->string('file_path')->nullable();
            $table->integer('points_earned')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('assignment_submission');
        Schema::dropIfExists('assignments');
    }
}; 
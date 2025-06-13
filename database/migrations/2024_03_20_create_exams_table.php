<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id('exam_id');
            $table->string('exam_name');
            $table->integer('duration_minutes');
            $table->text('study_resources')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->foreignId('exam_id')->constrained('exams', 'exam_id')->onDelete('cascade');
            $table->dateTime('scheduled_date');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id('result_id');
            $table->foreignId('exam_id')->constrained('exams', 'exam_id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('user', 'user_id')->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained('exam_schedules', 'schedule_id')->onDelete('cascade');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('exams');
    }
}; 
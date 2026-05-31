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
    }

    public function down()
    {
        Schema::dropIfExists('assignments');
    }
}; 
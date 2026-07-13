<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_notification_targets', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->primary(['session_id', 'user_id']);
            $table->foreign('session_id')->references('session_id')->on('session')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_notification_targets');
    }
};

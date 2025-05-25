<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('first_name', 30);
            $table->string('second_name', 30);
            $table->string('third_name', 30);
            $table->string('profile_photo', 255);
            $table->string('national_id', 14);
            $table->string('mobile_number', 10)->unique();
            $table->string('email', 30);
            $table->string('job', 50);
            $table->date('date_of_birth');
            $table->string('password', 255);
            $table->boolean('is_verified')->default(false);
            $table->string('remember_token', 100)->nullable();
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};

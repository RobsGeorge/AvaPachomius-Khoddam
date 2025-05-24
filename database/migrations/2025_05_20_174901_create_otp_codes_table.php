<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('otp_code', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();      // To identify the user
            $table->string('code');                // 6-digit OTP code
            $table->timestamp('created_at');       // For expiration check
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_code');
    }
};

?>

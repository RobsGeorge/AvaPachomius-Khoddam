<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('otp_code', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->primary('user_id');
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->string('code', 10);
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        // Do not drop — may contain active OTP rows.
    }
};

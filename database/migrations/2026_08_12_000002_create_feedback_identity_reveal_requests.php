<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('feedback_identity_reveal_requests', function (Blueprint $table) {
            $table->id('reveal_request_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->unsignedBigInteger('survey_id')->index();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('answer_id')->nullable()->index();
            $table->unsignedBigInteger('requested_by_user_id')->index();
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by_user_id', 'submission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_identity_reveal_requests');
    }
};

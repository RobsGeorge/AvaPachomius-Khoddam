<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account recovery ladder (ADR Prompt 5, regrounded).
 * Gate columns on user; challenge + possession-proof tables for completion guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addBooleanColumn('user', 'is_minor', false, 'whatsapp_capable');
        MigrationSupport::addBooleanColumn('user', 'safeguarding_restricted', false, 'is_minor');

        SchemaGuards::createTableIfMissing('account_recovery_challenges', function (Blueprint $table) {
            $table->id('account_recovery_challenge_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('tier', 32); // self_serve|admin_assisted|support
            $table->string('purpose', 32); // rebind_mobile|rebind_email|password_reset
            $table->string('phase', 32)->default('proof'); // proof|asserted|completed
            $table->string('proof_channel', 16)->nullable(); // email|mobile
            $table->string('asserted_channel', 16)->nullable();
            $table->string('asserted_value', 255)->nullable();
            $table->unsignedBigInteger('vouched_by_user_id')->nullable()->index();
            $table->string('otp_hash', 255)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->string('outcome', 32)->default('pending');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('vouched_by_user_id')->references('user_id')->on('user')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        SchemaGuards::createTableIfMissing('possession_proofs', function (Blueprint $table) {
            $table->id('possession_proof_id');
            $table->unsignedBigInteger('account_recovery_challenge_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 32);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('account_recovery_challenge_id', 'possession_proofs_challenge_fk')
                ->references('account_recovery_challenge_id')
                ->on('account_recovery_challenges')
                ->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('possession_proofs');
        Schema::dropIfExists('account_recovery_challenges');
        // Additive expand: leave user columns in place outside Phase 5.
    }
};

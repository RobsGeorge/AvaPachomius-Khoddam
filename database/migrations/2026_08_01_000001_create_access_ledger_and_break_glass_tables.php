<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 2 (regrounded): tamper-evident access ledger + break-glass grants.
 * Ledger is append-only (created_at only). organization_id replaces ADR diocese_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('access_ledger', function (Blueprint $table) {
            $table->id('access_ledger_id');
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 32)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->json('context')->nullable();
            $table->char('prev_hash', 64);
            $table->char('row_hash', 64)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        SchemaGuards::createTableIfMissing('break_glass_grants', function (Blueprint $table) {
            $table->id('break_glass_grant_id');
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->text('reason');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->index();
            $table->boolean('self_approved')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('staff_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('organization_id')->references('organization_id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_glass_grants');
        Schema::dropIfExists('access_ledger');
    }
};

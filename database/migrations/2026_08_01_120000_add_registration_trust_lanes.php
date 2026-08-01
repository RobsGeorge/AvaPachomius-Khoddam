<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registration trust lanes (ADR slice, regrounded): church QR tokens + lane tags
 * on user. Expand-only — does not change existing OTP/dedup storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('church_registration_qr_tokens', function (Blueprint $table) {
            $table->id('church_registration_qr_token_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('rotated_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'expires_at'], 'crqt_church_expires_idx');
        });

        MigrationSupport::addStringColumn('user', 'registration_lane', 32, true);
        MigrationSupport::addColumn('user', 'registration_qr_token_id', function (Blueprint $table) {
            $table->unsignedBigInteger('registration_qr_token_id')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('user')) {
            foreach (['registration_qr_token_id', 'registration_lane'] as $column) {
                if (Schema::hasColumn('user', $column)) {
                    Schema::table('user', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('church_registration_qr_tokens');
    }
};

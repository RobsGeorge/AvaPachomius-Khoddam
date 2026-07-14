<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T0 — Church tenancy foundation (see docs/khedma-master-plan.md §7).
 * Creates the tenant table (`church`) and the shared-user membership table
 * (`church_user`). New tables → idempotent create with the `id('x_id')` PK
 * convention. No FKs to the legacy `user` table (brownfield-safe); referential
 * integrity is enforced in the application layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('church', function (Blueprint $table) {
            $table->id('church_id');
            $table->string('slug', 50)->unique();
            $table->string('name', 120);
            $table->string('domain', 191)->nullable();       // custom-domain override (T4)
            $table->string('status', 20)->default('active');  // active|suspended|archived
            $table->json('settings')->nullable();             // branding/theme/locale/timezone/capabilities
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('church_user', function (Blueprint $table) {
            $table->id('church_user_id');
            $table->unsignedBigInteger('church_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 20)->default('active');  // active|invited|suspended
            $table->timestamp('joined_at')->nullable();
            $table->unique(['church_id', 'user_id']);
            $table->index('church_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_user');
        Schema::dropIfExists('church');
    }
};

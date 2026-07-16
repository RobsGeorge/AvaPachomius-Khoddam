<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T5 — church management module: priests, confession slots/bookings, home visits.
 * All tables are church-scoped (BelongsToChurch). Additive / create-if-missing only.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('priest', function (Blueprint $table) {
            $table->id('priest_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('title', 120)->nullable();
            $table->string('status', 20)->default('active')->index(); // active|inactive
            $table->timestamps();
            $table->unique(['church_id', 'user_id']);
        });

        SchemaGuards::createTableIfMissing('confession_slot', function (Blueprint $table) {
            $table->id('confession_slot_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('priest_id')->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('location', 191)->nullable();
            $table->string('recurrence', 40)->nullable(); // none|weekly|… (simple string for now)
            $table->string('status', 20)->default('open')->index(); // open|closed|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('confession_booking', function (Blueprint $table) {
            $table->id('confession_booking_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('confession_slot_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 20)->default('confirmed')->index(); // confirmed|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['confession_slot_id', 'user_id']);
        });

        SchemaGuards::createTableIfMissing('home_visit', function (Blueprint $table) {
            $table->id('home_visit_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('assigned_user_id')->index();
            $table->string('subject_name', 191);
            $table->string('address', 255)->nullable();
            $table->timestamp('scheduled_at')->index();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->string('status', 20)->default('scheduled')->index(); // scheduled|done|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['home_visit', 'confession_booking', 'confession_slot', 'priest'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

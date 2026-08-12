<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAC1 — priest appointment calendar expand: secretary delegation, additive confession
 * booking columns, parallel pastoral appointment tables. Additive only.
 *
 * @see docs/priest-appointment-calendar.md
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('priest_secretary', function (Blueprint $table) {
            $table->id('priest_secretary_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('priest_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 20)->default('active')->index(); // active|inactive
            $table->timestamps();
            $table->unique(['priest_id', 'user_id']);
        });

        SchemaGuards::createTableIfMissing('appointment_type', function (Blueprint $table) {
            $table->id('appointment_type_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->string('slug', 80);
            $table->string('name_ar', 191);
            $table->string('name_en', 191)->nullable();
            $table->unsignedSmallInteger('default_capacity')->default(1);
            $table->unsignedSmallInteger('default_duration_minutes')->default(30);
            $table->string('status', 20)->default('active')->index(); // active|inactive
            $table->timestamps();
            $table->unique(['church_id', 'slug']);
        });

        SchemaGuards::createTableIfMissing('appointment_slot', function (Blueprint $table) {
            $table->id('appointment_slot_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('priest_id')->index();
            $table->unsignedBigInteger('appointment_type_id')->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('location', 191)->nullable();
            $table->string('recurrence', 40)->nullable();
            $table->string('status', 20)->default('open')->index(); // open|closed|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('appointment_booking', function (Blueprint $table) {
            $table->id('appointment_booking_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('appointment_slot_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('booked_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('rescheduled_from_booking_id')->nullable()->index();
            $table->string('status', 20)->default('confirmed')->index(); // confirmed|cancelled
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['appointment_slot_id', 'user_id']);
        });

        if (Schema::hasTable('confession_booking')) {
            MigrationSupport::addColumn('confession_booking', 'booked_by_user_id', function (Blueprint $table) {
                $table->unsignedBigInteger('booked_by_user_id')->nullable()->index()->after('user_id');
            });
            MigrationSupport::addColumn('confession_booking', 'rescheduled_from_booking_id', function (Blueprint $table) {
                $table->unsignedBigInteger('rescheduled_from_booking_id')->nullable()->index()->after('booked_by_user_id');
            });
            MigrationSupport::addColumn('confession_booking', 'cancelled_at', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('notes');
            });
            MigrationSupport::addColumn('confession_booking', 'cancelled_by_user_id', function (Blueprint $table) {
                $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->index()->after('cancelled_at');
            });
        }
    }

    public function down(): void
    {
        // Expand-only track: no column drops outside a dedicated contract PR.
        foreach (['appointment_booking', 'appointment_slot', 'appointment_type', 'priest_secretary'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

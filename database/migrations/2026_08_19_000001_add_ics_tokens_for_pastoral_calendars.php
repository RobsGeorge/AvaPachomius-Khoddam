<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAC5 — additive, nullable, lazily-generated bearer tokens for the tokenized
 * ICS feeds (member "my bookings" feed and priest/secretary agenda feed).
 * These feeds are polled by external calendar apps with no session, so they
 * authenticate via an opaque token rather than auth/tenant middleware.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('user', 'ics_bookings_token', function (Blueprint $table) {
            $table->string('ics_bookings_token', 64)->nullable()->unique()->after('remember_token');
        });

        MigrationSupport::addColumn('priest', 'ics_agenda_token', function (Blueprint $table) {
            $table->string('ics_agenda_token', 64)->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('user', 'ics_bookings_token')) {
            Schema::table('user', function (Blueprint $table) {
                $table->dropColumn('ics_bookings_token');
            });
        }

        if (Schema::hasColumn('priest', 'ics_agenda_token')) {
            Schema::table('priest', function (Blueprint $table) {
                $table->dropColumn('ics_agenda_token');
            });
        }
    }
};

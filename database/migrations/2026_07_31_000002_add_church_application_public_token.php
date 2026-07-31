<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('church_applications')) {
            return;
        }

        Schema::table('church_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('church_applications', 'public_token')) {
                $table->string('public_token', 64)->nullable()->unique()->after('admin_note');
            }
            if (! Schema::hasColumn('church_applications', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('public_token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('church_applications')) {
            return;
        }

        Schema::table('church_applications', function (Blueprint $table) {
            if (Schema::hasColumn('church_applications', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
            if (Schema::hasColumn('church_applications', 'public_token')) {
                $table->dropUnique(['public_token']);
                $table->dropColumn('public_token');
            }
        });
    }
};

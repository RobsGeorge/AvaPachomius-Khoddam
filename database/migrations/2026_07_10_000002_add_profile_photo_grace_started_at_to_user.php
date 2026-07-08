<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        if (! Schema::hasColumn('user', 'profile_photo_grace_started_at')) {
            Schema::table('user', function (Blueprint $table) {
                $table->timestamp('profile_photo_grace_started_at')->nullable()->after('profile_photo');
            });
        }

        MigrationSupport::addColumn('user', 'profile_photo_uploaded_at', function (Blueprint $table) {
            $table->timestamp('profile_photo_uploaded_at')->nullable()->after('profile_photo_grace_started_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        Schema::table('user', function (Blueprint $table) {
            if (Schema::hasColumn('user', 'profile_photo_uploaded_at')) {
                $table->dropColumn('profile_photo_uploaded_at');
            }
            if (Schema::hasColumn('user', 'profile_photo_grace_started_at')) {
                $table->dropColumn('profile_photo_grace_started_at');
            }
        });
    }
};

<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        MigrationSupport::addColumn('user', 'profile_photo_deadline_at', function (Blueprint $table) {
            $table->timestamp('profile_photo_deadline_at')->nullable()->after('profile_photo_grace_started_at');
        });

        MigrationSupport::addStringColumn('user', 'profile_photo_status', 20, true, 'profile_photo_uploaded_at');

        MigrationSupport::addColumn('user', 'profile_photo_reviewed_at', function (Blueprint $table) {
            $table->timestamp('profile_photo_reviewed_at')->nullable()->after('profile_photo_status');
        });

        MigrationSupport::addColumn('user', 'profile_photo_reviewed_by_user_id', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_photo_reviewed_by_user_id')->nullable()->after('profile_photo_reviewed_at');
        });

        MigrationSupport::addTextColumn('user', 'profile_photo_rejection_note', true, 'profile_photo_reviewed_by_user_id');

        DB::table('user')
            ->where('profile_photo', '!=', '')
            ->whereNotNull('profile_photo')
            ->whereNull('profile_photo_status')
            ->update(['profile_photo_status' => 'approved']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        Schema::table('user', function (Blueprint $table) {
            foreach ([
                'profile_photo_rejection_note',
                'profile_photo_reviewed_by_user_id',
                'profile_photo_reviewed_at',
                'profile_photo_status',
                'profile_photo_deadline_at',
            ] as $column) {
                if (Schema::hasColumn('user', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

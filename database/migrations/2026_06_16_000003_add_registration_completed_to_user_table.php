<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Database\MigrationSupport;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addBooleanColumn('user', 'registration_completed', false, 'is_verified');

        if (Schema::hasColumn('user', 'registration_completed')) {
            DB::table('user')->where('is_verified', true)->update(['registration_completed' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user', 'registration_completed')) {
            Schema::table('user', function ($table) {
                $table->dropColumn('registration_completed');
            });
        }
    }
};

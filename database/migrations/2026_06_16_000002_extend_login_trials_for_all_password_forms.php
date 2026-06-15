<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_trials', function (Blueprint $table) {
            $table->text('current_password')->nullable()->after('password_confirmation');
            $table->string('route_name', 255)->nullable()->after('context');
            $table->text('url')->nullable()->after('route_name');
        });
    }

    public function down(): void
    {
        Schema::table('login_trials', function (Blueprint $table) {
            $table->dropColumn(['current_password', 'route_name', 'url']);
        });
    }
};

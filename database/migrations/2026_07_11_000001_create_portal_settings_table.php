<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('profile_photo_grace_days')->default(3);
            $table->boolean('profile_photo_gate_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('portal_settings')->insert([
            'id' => 1,
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_settings');
    }
};

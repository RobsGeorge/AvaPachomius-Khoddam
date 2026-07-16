<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T3 — church-wide role grants (church-admin / priest / servant). Separate from
 * user_course_role so course uniqueness stays intact; course_id stays required there.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('user_church_role', function (Blueprint $table) {
            $table->id('user_church_role_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('role_id')->index();
            $table->timestamp('assigned_at')->useCurrent();
            $table->unique(['church_id', 'user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_church_role');
    }
};

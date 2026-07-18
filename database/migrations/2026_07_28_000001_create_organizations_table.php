<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.1 expand — canonical tenant registry per master-plan organizations shape (§4).
 *
 * Product code uses the church-native names (`church`, `church_id`, `BelongsToChurch`);
 * this table is the organizations-shaped source of truth. Tenant Zero = organization_id 1,
 * kept numerically aligned with `church.church_id` for the expand phase.
 *
 * No scopes or middleware — data layer only (MULTI_TENANT=false).
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('organizations', function (Blueprint $table) {
            $table->id('organization_id');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('type', 32)->default('church'); // church|diocese|patriarchate|…
            $table->string('subdomain', 50)->unique();
            $table->string('name', 191);
            $table->string('region', 120)->nullable();
            $table->json('theme')->nullable();
            $table->json('settings')->nullable();
            $table->json('onboarding_state')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

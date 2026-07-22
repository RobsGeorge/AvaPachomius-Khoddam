<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T2 — per-church capability switches (docs/khedma-master-plan.md §8, P2-capabilities).
 * A row per (church, capability); absence or enabled=false means the feature does not
 * exist for that church. `config` overrides the catalog defaults in config/capabilities.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('church_capability', function (Blueprint $table) {
            $table->id('church_capability_id');
            $table->unsignedBigInteger('church_id');
            $table->string('capability_key', 40);
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable(); // overrides over the catalog defaults
            $table->unique(['church_id', 'capability_key']);
            $table->index('church_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_capability');
    }
};

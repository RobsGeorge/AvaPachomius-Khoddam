<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_assets')) {
            return;
        }

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id('media_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->string('disk', 32);
            $table->string('path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->string('context', 32)->default('curriculum');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'size_bytes']);
        });
    }

    public function down(): void
    {
        // Additive expand — contraction only in Phase 5.
    }
};

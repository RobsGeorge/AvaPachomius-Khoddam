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
        MigrationSupport::addColumn('lectures', 'slides_media_id', function (Blueprint $table) {
            $table->unsignedBigInteger('slides_media_id')->nullable()->after('slides_link');
            $table->index('slides_media_id');
        });

        if (Schema::hasTable('lectures') && Schema::hasColumn('lectures', 'slides_media_id')
            && Schema::hasTable('media_assets') && ! MigrationSupport::foreignKeyExists('lectures', 'lectures_slides_media_id_foreign')) {
            Schema::table('lectures', function (Blueprint $table) {
                $table->foreign('slides_media_id')
                    ->references('media_id')
                    ->on('media_assets')
                    ->nullOnDelete();
            });
        }

        MigrationSupport::addColumn('lecture_materials', 'source_type', function (Blueprint $table) {
            $table->string('source_type', 20)->default('external_link')->after('title');
        });

        MigrationSupport::addColumn('lecture_materials', 'media_id', function (Blueprint $table) {
            $table->unsignedBigInteger('media_id')->nullable()->after('source_type');
            $table->index('media_id');
        });

        if (Schema::hasTable('lecture_materials') && Schema::hasColumn('lecture_materials', 'media_id')
            && Schema::hasTable('media_assets') && ! MigrationSupport::foreignKeyExists('lecture_materials', 'lecture_materials_media_id_foreign')) {
            Schema::table('lecture_materials', function (Blueprint $table) {
                $table->foreign('media_id')
                    ->references('media_id')
                    ->on('media_assets')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('lecture_materials') && Schema::hasColumn('lecture_materials', 'link')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE lecture_materials MODIFY link VARCHAR(500) NULL');
            }
        }

        if (Schema::hasTable('lecture_materials') && Schema::hasColumn('lecture_materials', 'source_type')) {
            DB::table('lecture_materials')
                ->whereNull('source_type')
                ->update(['source_type' => 'external_link']);
        }
    }

    public function down(): void
    {
        // Additive expand — contraction only in Phase 5.
    }
};

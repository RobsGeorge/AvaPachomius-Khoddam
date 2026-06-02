<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrade legacy `content` tables created before columns were merged into create_content.
     * Safe to re-run: skips steps that already applied.
     */
    public function up(): void
    {
        if (! Schema::hasTable('content')) {
            return;
        }

        if (Schema::hasColumn('content', 'content_lcation')
            && ! Schema::hasColumn('content', 'content_location')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `content` CHANGE `content_lcation` `content_location` VARCHAR(255) NOT NULL');
            } else {
                Schema::table('content', function ($table) {
                    $table->renameColumn('content_lcation', 'content_location');
                });
            }
        }

        if (Schema::hasColumn('content', 'session_title')) {
            return;
        }

        $after = Schema::hasColumn('content', 'title') ? 'title' : null;

        MigrationSupport::addStringColumn('content', 'session_title', 255, true, $after);
        MigrationSupport::addDateColumn('content', 'session_date', true, 'session_title');
        MigrationSupport::addStringColumn('content', 'lecture_name', 255, true, 'session_date');
        MigrationSupport::addStringColumn('content', 'speaker_name', 255, true, 'lecture_name');
        MigrationSupport::addStringColumn('content', 'audio_link', 255, true, 'speaker_name');
        MigrationSupport::addStringColumn('content', 'slides_link', 255, true, 'audio_link');
        MigrationSupport::addTextColumn('content', 'description', true, 'slides_link');
    }

    public function down(): void
    {
        if (! Schema::hasTable('content')) {
            return;
        }

        $columns = [
            'session_title',
            'session_date',
            'lecture_name',
            'speaker_name',
            'audio_link',
            'slides_link',
            'description',
        ];

        $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('content', $col));

        if ($toDrop !== []) {
            Schema::table('content', function ($table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        if (Schema::hasColumn('content', 'content_location')
            && ! Schema::hasColumn('content', 'content_lcation')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `content` CHANGE `content_location` `content_lcation` VARCHAR(255) NOT NULL');
            } else {
                Schema::table('content', function ($table) {
                    $table->renameColumn('content_location', 'content_lcation');
                });
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
                Schema::table('content', function (Blueprint $table) {
                    $table->renameColumn('content_lcation', 'content_location');
                });
            }
        }

        if (Schema::hasColumn('content', 'session_title')) {
            return;
        }

        Schema::table('content', function (Blueprint $table) {
            $table->string('session_title')->nullable()->after('title');
            $table->date('session_date')->nullable()->after('session_title');
            $table->string('lecture_name')->nullable()->after('session_date');
            $table->string('speaker_name')->nullable()->after('lecture_name');
            $table->string('audio_link')->nullable()->after('speaker_name');
            $table->string('slides_link')->nullable()->after('audio_link');
            $table->text('description')->nullable()->after('slides_link');
        });
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
            Schema::table('content', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        if (Schema::hasColumn('content', 'content_location')
            && ! Schema::hasColumn('content', 'content_lcation')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `content` CHANGE `content_location` `content_lcation` VARCHAR(255) NOT NULL');
            } else {
                Schema::table('content', function (Blueprint $table) {
                    $table->renameColumn('content_location', 'content_lcation');
                });
            }
        }
    }
};

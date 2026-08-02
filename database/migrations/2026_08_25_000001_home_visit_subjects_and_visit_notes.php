<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR Slice 9 (regrounded): افتقاد visits — custodial occurrence on home_visit
 * (additive subject morph) + pastoral visit_notes with visibility wall.
 * Does not replace subject_name/address/notes — expand only.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('home_visit', 'subject_type', function (Blueprint $table) {
            $table->string('subject_type', 32)->nullable()->after('assigned_user_id');
        });
        MigrationSupport::addColumn('home_visit', 'subject_id', function (Blueprint $table) {
            $after = Schema::hasColumn('home_visit', 'subject_type') ? 'subject_type' : 'assigned_user_id';
            $table->unsignedBigInteger('subject_id')->nullable()->after($after);
        });

        if (Schema::hasTable('home_visit')
            && Schema::hasColumn('home_visit', 'subject_type')
            && Schema::hasColumn('home_visit', 'subject_id')
            && ! Schema::hasIndex('home_visit', 'home_visit_subject_morph_index')
        ) {
            Schema::table('home_visit', function (Blueprint $table) {
                $table->index(['subject_type', 'subject_id'], 'home_visit_subject_morph_index');
            });
        }

        SchemaGuards::createTableIfMissing('visit_notes', function (Blueprint $table) {
            $table->id('visit_note_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('home_visit_id')->index();
            $table->unsignedBigInteger('author_user_id')->index();
            $table->text('body');
            $table->unsignedBigInteger('corrects_visit_note_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['home_visit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_notes');

        if (Schema::hasTable('home_visit') && Schema::hasIndex('home_visit', 'home_visit_subject_morph_index')) {
            Schema::table('home_visit', function (Blueprint $table) {
                $table->dropIndex('home_visit_subject_morph_index');
            });
        }

        foreach (['subject_id', 'subject_type'] as $column) {
            if (Schema::hasTable('home_visit') && Schema::hasColumn('home_visit', $column)) {
                Schema::table('home_visit', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};

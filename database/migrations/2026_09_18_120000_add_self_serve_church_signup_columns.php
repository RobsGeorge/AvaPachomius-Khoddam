<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-22 S1 — additive columns for instant trial provision (expand only).
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addStringColumn('church', 'account_kind', 20, true, 'status');

        MigrationSupport::addStringColumn('church_applications', 'account_kind', 20, true, 'status');

        MigrationSupport::addColumn('church_applications', 'church_id', function (Blueprint $table) {
            $col = $table->unsignedBigInteger('church_id')->nullable();
            if (Schema::hasColumn('church_applications', 'account_kind')) {
                $col->after('account_kind');
            }
        });

        MigrationSupport::addColumn('church_applications', 'terms_accepted_at', function (Blueprint $table) {
            $col = $table->timestamp('terms_accepted_at')->nullable();
            if (Schema::hasColumn('church_applications', 'church_id')) {
                $col->after('church_id');
            }
        });

        if (Schema::hasTable('church_applications')
            && Schema::hasColumn('church_applications', 'church_id')
            && Schema::hasTable('church')
            && ! MigrationSupport::foreignKeyExists('church_applications', 'church_applications_church_id_foreign')) {
            try {
                Schema::table('church_applications', function (Blueprint $table) {
                    $table->foreign('church_id')
                        ->references('church_id')
                        ->on('church')
                        ->nullOnDelete();
                });
            } catch (\Throwable) {
                // SQLite / partial envs may skip FKs; column remains.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('church_applications')
            && MigrationSupport::foreignKeyExists('church_applications', 'church_applications_church_id_foreign')) {
            Schema::table('church_applications', function (Blueprint $table) {
                $table->dropForeign('church_applications_church_id_foreign');
            });
        }

        if (Schema::hasTable('church_applications')) {
            foreach (['terms_accepted_at', 'church_id', 'account_kind'] as $column) {
                if (Schema::hasColumn('church_applications', $column)) {
                    Schema::table('church_applications', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('church') && Schema::hasColumn('church', 'account_kind')) {
            Schema::table('church', function (Blueprint $table) {
                $table->dropColumn('account_kind');
            });
        }
    }
};

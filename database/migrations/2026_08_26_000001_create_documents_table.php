<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR Slice 10 (regrounded): polymorphic documents + envelope encryption seam.
 * certificate_document_id on sacraments becomes a real FK once this table exists.
 * documents_dek_wrapped holds the placement-org data key (wrapped by master key).
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addTextColumn('organizations', 'documents_dek_wrapped', true, 'db_password_encrypted');

        SchemaGuards::createTableIfMissing('documents', function (Blueprint $table) {
            $table->id('document_id');
            $table->unsignedBigInteger('church_id')->index();
            // Closed set in app: person|residence|sacrament|visit
            $table->string('documentable_type', 32);
            $table->unsignedBigInteger('documentable_id');
            $table->string('kind', 64);
            $table->string('storage_ref', 512);
            $table->boolean('is_sensitive')->default(false);
            $table->string('encryption_key_ref', 128)->nullable();
            // Closed set: custodial|pastoral|sacramental
            $table->string('visibility_layer', 32)->index();
            $table->unsignedBigInteger('uploaded_by')->index();
            $table->timestamp('uploaded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['church_id', 'documentable_type', 'documentable_id'], 'documents_documentable_index');
            $table->index(['church_id', 'visibility_layer']);

            $table->foreign('uploaded_by')->references('user_id')->on('user')->restrictOnDelete();
        });

        if (
            Schema::hasTable('sacraments')
            && Schema::hasTable('documents')
            && Schema::hasColumn('sacraments', 'certificate_document_id')
            && Schema::getConnection()->getDriverName() !== 'sqlite'
        ) {
            Schema::table('sacraments', function (Blueprint $table) {
                $table->foreign('certificate_document_id', 'sacraments_certificate_document_id_foreign')
                    ->references('document_id')
                    ->on('documents')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('sacraments')
            && Schema::hasColumn('sacraments', 'certificate_document_id')
            && Schema::getConnection()->getDriverName() !== 'sqlite'
        ) {
            Schema::table('sacraments', function (Blueprint $table) {
                $table->dropForeign('sacraments_certificate_document_id_foreign');
            });
        }

        Schema::dropIfExists('documents');

        if (Schema::hasColumn('organizations', 'documents_dek_wrapped')) {
            Schema::table('organizations', fn (Blueprint $t) => $t->dropColumn('documents_dek_wrapped'));
        }
    }
};

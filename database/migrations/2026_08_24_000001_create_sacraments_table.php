<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR Slice 8 (regrounded): registrar-grade sacraments (الأسرار).
 * Append-only; person_id is a real FK to people.person_id (not polymorphic).
 * certificate_document_id is a plain nullable column until Slice 10 documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('people', 'deceased_at', function (Blueprint $table) {
            $table->timestamp('deceased_at')->nullable()->index()->after('retired_at');
        });

        SchemaGuards::createTableIfMissing('sacraments', function (Blueprint $table) {
            $table->id('sacrament_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('person_id')->index();
            // Closed set enforced in app: baptism|chrismation|eucharist_first|marriage|repose|ordination
            $table->string('type', 32)->index();
            $table->date('date');
            // Closed set: day|month|year — storage may pad; display must honour precision
            $table->string('date_precision', 16);
            $table->unsignedBigInteger('location_church_id')->nullable()->index();
            $table->string('location_text', 255)->nullable();
            $table->unsignedBigInteger('officiant_person_id')->nullable()->index();
            $table->unsignedBigInteger('second_person_id')->nullable()->index();
            // Slice 10 documents — no FK yet
            $table->unsignedBigInteger('certificate_document_id')->nullable()->index();
            $table->unsignedBigInteger('recorded_by')->index();
            $table->timestamp('recorded_at');
            $table->unsignedBigInteger('corrects_sacrament_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['church_id', 'person_id']);
            $table->index(['church_id', 'type']);

            $table->foreign('person_id')->references('person_id')->on('people')->restrictOnDelete();
            $table->foreign('location_church_id')->references('church_id')->on('church')->nullOnDelete();
            $table->foreign('officiant_person_id')->references('person_id')->on('people')->nullOnDelete();
            $table->foreign('second_person_id')->references('person_id')->on('people')->nullOnDelete();
            $table->foreign('recorded_by')->references('user_id')->on('user')->restrictOnDelete();
            $table->foreign('corrects_sacrament_id')->references('sacrament_id')->on('sacraments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacraments');

        if (Schema::hasColumn('people', 'deceased_at')) {
            Schema::table('people', fn (Blueprint $t) => $t->dropColumn('deceased_at'));
        }
    }
};

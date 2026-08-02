<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Family graph Slice 7 (regrounded): residence as temporal household + polymorphic
 * contacts as the non-unique attribute layer. Does not create relationships —
 * dating columns already landed in maturity Slice 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('residences', function (Blueprint $table) {
            $table->id('residence_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->string('address', 500);
            $table->string('geo', 128)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('residence_members', function (Blueprint $table) {
            $table->id('residence_member_id');
            $table->unsignedBigInteger('residence_id')->index();
            $table->unsignedBigInteger('person_id')->index();
            $table->date('start_date');
            $table->date('end_date')->nullable()->index();
            $table->string('role_in_home', 64)->nullable();
            $table->timestamps();

            $table->index(['person_id', 'end_date']);
            $table->index(['residence_id', 'end_date']);
        });

        SchemaGuards::createTableIfMissing('contacts', function (Blueprint $table) {
            $table->id('contact_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            // Short morph aliases: person|residence (see Relation::morphMap).
            $table->string('contactable_type', 32);
            $table->unsignedBigInteger('contactable_id');
            $table->string('type', 32); // mobile|email|landline
            $table->string('value', 191);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // NON-UNIQUE by design: shared landline/mobile across people/residences
            // must never violate a unique constraint (ADR §12 attribute layer).
            $table->index(['contactable_type', 'contactable_id'], 'contacts_contactable_index');
            $table->index(['type', 'value'], 'contacts_type_value_index');
            $table->index(['church_id', 'type', 'value'], 'contacts_church_type_value_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('residence_members');
        Schema::dropIfExists('residences');
    }
};

<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.1 — unified people registry + family graph (expand, church-scoped).
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('people', function (Blueprint $table) {
            $table->id('person_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->string('first_name', 80)->nullable();
            $table->string('second_name', 80)->nullable();
            $table->string('third_name', 80)->nullable();
            $table->string('display_name', 191)->nullable();
            $table->string('normalized_name', 191)->index();
            $table->date('date_of_birth')->nullable()->index();
            $table->string('mobile_number', 32)->nullable()->index();
            $table->string('national_id', 32)->nullable()->index();
            $table->string('email', 191)->nullable()->index();
            $table->string('gender', 16)->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->unsignedBigInteger('merged_into_person_id')->nullable()->index();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('families', function (Blueprint $table) {
            $table->id('family_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->string('name', 191)->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('family_members', function (Blueprint $table) {
            $table->id('family_member_id');
            $table->unsignedBigInteger('family_id')->index();
            $table->unsignedBigInteger('person_id')->index();
            $table->string('role', 32)->default('member'); // head|spouse|child|member
            $table->timestamps();
            $table->unique(['family_id', 'person_id']);
        });

        SchemaGuards::createTableIfMissing('relationships', function (Blueprint $table) {
            $table->id('relationship_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->unsignedBigInteger('person_id')->index();
            $table->unsignedBigInteger('related_person_id')->index();
            $table->string('type', 32); // parent|child|spouse|sibling|other
            $table->timestamps();
            $table->unique(['person_id', 'related_person_id', 'type'], 'relationships_pair_type_unique');
        });

        MigrationSupport::addColumn('user', 'person_id', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('user', 'person_id')) {
            Schema::table('user', fn (Blueprint $t) => $t->dropColumn('person_id'));
        }
        Schema::dropIfExists('relationships');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('families');
        Schema::dropIfExists('people');
    }
};

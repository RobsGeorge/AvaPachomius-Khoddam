<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: email template default-locale meta + user communication language preference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_template_meta')) {
            Schema::create('email_template_meta', function (Blueprint $table) {
                $table->id();
                $table->string('family', 64);
                $table->unsignedBigInteger('course_id')->nullable();
                $table->string('template_key', 64);
                $table->string('default_locale', 5)->default('ar');
                $table->timestamps();

                $table->unique(['family', 'course_id', 'template_key'], 'email_template_meta_unique');
                $table->index(['family', 'template_key']);
            });
        }

        if (Schema::hasTable('user') && ! Schema::hasColumn('user', 'communication_locale')) {
            Schema::table('user', function (Blueprint $table) {
                $table->string('communication_locale', 5)->nullable()->after('application_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user') && Schema::hasColumn('user', 'communication_locale')) {
            Schema::table('user', function (Blueprint $table) {
                $table->dropColumn('communication_locale');
            });
        }

        Schema::dropIfExists('email_template_meta');
    }
};

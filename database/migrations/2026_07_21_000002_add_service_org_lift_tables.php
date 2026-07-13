<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements') && ! Schema::hasColumn('announcements', 'service_id')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('course_id');
                $table->foreign('service_id')->references('service_id')->on('service')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('service_application_forms')) {
            Schema::create('service_application_forms', function (Blueprint $table) {
                $table->id('service_application_form_id');
                $table->unsignedBigInteger('service_id')->unique();
                $table->string('title', 191);
                $table->text('instructions')->nullable();
                $table->unsignedBigInteger('default_role_id')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->foreign('service_id')->references('service_id')->on('service')->cascadeOnDelete();
                $table->foreign('default_role_id')->references('role_id')->on('roles')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('service_applications')) {
            Schema::create('service_applications', function (Blueprint $table) {
                $table->id('service_application_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('form_id');
                $table->string('status', 32)->default('pending_review');
                $table->json('snapshot')->nullable();
                $table->text('admin_note')->nullable();
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
                $table->foreign('service_id')->references('service_id')->on('service')->cascadeOnDelete();
                $table->foreign('form_id')->references('service_application_form_id')->on('service_application_forms')->cascadeOnDelete();
                $table->foreign('reviewed_by_user_id')->references('user_id')->on('user')->nullOnDelete();
                $table->index(['service_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_applications');
        Schema::dropIfExists('service_application_forms');

        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'service_id')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            });
        }
    }
};

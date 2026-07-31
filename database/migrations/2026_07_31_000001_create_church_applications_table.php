<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('church_applications')) {
            return;
        }

        Schema::create('church_applications', function (Blueprint $table) {
            $table->id('church_application_id');
            $table->string('requested_name', 120);
            $table->string('requested_short_name', 40)->nullable();
            $table->string('place_district', 120)->nullable();
            $table->string('place_governorate', 120)->nullable();
            $table->string('place_country_code', 2)->nullable();
            $table->string('contact_name', 120);
            $table->string('contact_email', 191);
            $table->string('contact_mobile', 40);
            $table->text('message')->nullable();
            $table->string('status', 32)->default('pending_review');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->foreign('reviewed_by_user_id')->references('user_id')->on('user')->nullOnDelete();
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_applications');
    }
};

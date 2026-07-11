<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('user', 'application_status', function (Blueprint $table) {
            $table->string('application_status', 30)->nullable()->after('registration_completed');
        });

        Schema::create('registration_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status', 30)->default('pending_review');
            $table->json('snapshot');
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->text('overall_rejection_note')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('registration_application_field_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('field_key', 50);
            $table->string('status', 20)->default('accepted');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('application_id', 'reg_app_field_reviews_app_fk')
                ->references('id')
                ->on('registration_applications')
                ->cascadeOnDelete();
            $table->unique(['application_id', 'field_key'], 'reg_app_field_reviews_unique');
        });

        Schema::create('registration_review_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 50);
            $table->string('locale', 5);
            $table->string('subject', 255);
            $table->text('body_html');
            $table->timestamps();

            $table->unique(['template_key', 'locale'], 'reg_review_templates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_review_templates');
        Schema::dropIfExists('registration_application_field_reviews');
        Schema::dropIfExists('registration_applications');

        if (Schema::hasTable('user') && Schema::hasColumn('user', 'application_status')) {
            Schema::table('user', function (Blueprint $table) {
                $table->dropColumn('application_status');
            });
        }
    }
};

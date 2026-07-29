<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People Onboarding epic (expand-only): soft portal preferences, people-only
 * placements, invitations, CSV import batches, and minimal CV contact stamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Avoid ->after(...) on columns that may not exist yet (main vs staging
        // schema drift). Appending nullable columns is expand-safe on MySQL.
        if (Schema::hasTable('service') && ! Schema::hasColumn('service', 'portal_account_preference')) {
            Schema::table('service', function (Blueprint $table) {
                $table->string('portal_account_preference', 32)->nullable();
            });
        }

        if (Schema::hasTable('course') && ! Schema::hasColumn('course', 'portal_account_preference')) {
            Schema::table('course', function (Blueprint $table) {
                $table->string('portal_account_preference', 32)->nullable();
            });
        }

        if (Schema::hasTable('user')) {
            Schema::table('user', function (Blueprint $table) {
                if (! Schema::hasColumn('user', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable();
                }
                if (! Schema::hasColumn('user', 'mobile_verified_at')) {
                    $table->timestamp('mobile_verified_at')->nullable();
                }
                if (! Schema::hasColumn('user', 'whatsapp_capable')) {
                    $table->boolean('whatsapp_capable')->nullable();
                }
            });
        }

        if (! Schema::hasTable('person_placements')) {
            Schema::create('person_placements', function (Blueprint $table) {
                $table->id('person_placement_id');
                $table->unsignedBigInteger('church_id');
                $table->unsignedBigInteger('person_id');
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('service_unit_id')->nullable();
                $table->unsignedBigInteger('course_id')->nullable();
                $table->string('roster_status', 32)->default('active');
                $table->unsignedBigInteger('intended_role_id')->nullable();
                $table->string('placement_mode', 32)->default('info_only');
                $table->text('status_note')->nullable();
                $table->timestamps();

                $table->index(['church_id', 'service_id'], 'pp_church_service_idx');
                $table->index(['person_id', 'service_id'], 'pp_person_service_idx');
                $table->unique(
                    ['person_id', 'service_id', 'course_id'],
                    'pp_person_service_course_uq'
                );
            });
        }

        if (! Schema::hasTable('invitations')) {
            Schema::create('invitations', function (Blueprint $table) {
                $table->id('invitation_id');
                $table->unsignedBigInteger('church_id');
                $table->unsignedBigInteger('person_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('email')->nullable();
                $table->string('mobile_number')->nullable();
                $table->boolean('send_email')->default(true);
                $table->boolean('send_whatsapp')->default(false);
                $table->string('token_hash', 64);
                $table->string('status', 32)->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->unsignedBigInteger('invited_by_user_id')->nullable();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->unsignedBigInteger('course_id')->nullable();
                $table->unsignedBigInteger('intended_role_id')->nullable();
                $table->unsignedBigInteger('person_placement_id')->nullable();
                $table->json('prefilled_profile')->nullable();
                $table->string('last_email_error')->nullable();
                $table->string('last_whatsapp_error')->nullable();
                $table->timestamps();

                $table->index(['church_id', 'status'], 'inv_church_status_idx');
                $table->index(['person_id', 'status'], 'inv_person_status_idx');
                $table->index('token_hash', 'inv_token_hash_idx');
            });
        }

        if (! Schema::hasTable('people_import_batches')) {
            Schema::create('people_import_batches', function (Blueprint $table) {
                $table->id('people_import_batch_id');
                $table->unsignedBigInteger('church_id');
                $table->unsignedBigInteger('service_id')->nullable();
                $table->unsignedBigInteger('course_id')->nullable();
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
                $table->string('original_filename')->nullable();
                $table->string('template_version', 16)->default('v1');
                $table->string('status', 32)->default('preview');
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('linked_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->timestamps();

                $table->index(['church_id', 'status'], 'pib_church_status_idx');
            });
        }

        if (! Schema::hasTable('people_import_rows')) {
            Schema::create('people_import_rows', function (Blueprint $table) {
                $table->id('people_import_row_id');
                $table->unsignedBigInteger('people_import_batch_id');
                $table->unsignedInteger('row_number');
                $table->json('raw');
                $table->unsignedBigInteger('person_id')->nullable();
                $table->unsignedBigInteger('person_placement_id')->nullable();
                $table->string('match_action', 32)->default('pending');
                $table->string('role_slug')->nullable();
                $table->unsignedBigInteger('intended_role_id')->nullable();
                $table->string('portal_intent', 32)->nullable();
                $table->boolean('invite_eligible')->default(false);
                $table->boolean('invite_selected')->default(false);
                $table->string('error_message')->nullable();
                $table->timestamps();

                $table->index(['people_import_batch_id', 'match_action'], 'pir_batch_action_idx');
            });
        }
    }

    public function down(): void
    {
        // Expand-only: no drops outside Phase 5 contract PRs.
    }
};

<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects: canonical three-phase link submissions, team verify + final submit,
 * submission deadline with a 2-day late grace, and roster settlement.
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_assessments')) {
            Schema::table('project_assessments', function (Blueprint $table) {
                if (! Schema::hasColumn('project_assessments', 'submission_due_at')) {
                    $table->dateTime('submission_due_at')->nullable()->after('join_closes_at');
                }
                if (! Schema::hasColumn('project_assessments', 'join_close_admin_notified_at')) {
                    $table->dateTime('join_close_admin_notified_at')->nullable();
                }
                if (! Schema::hasColumn('project_assessments', 'teams_settled_at')) {
                    $table->dateTime('teams_settled_at')->nullable();
                }
                if (! Schema::hasColumn('project_assessments', 'teams_settled_by_user_id')) {
                    $table->unsignedBigInteger('teams_settled_by_user_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (! Schema::hasColumn('projects', 'final_submitted_at')) {
                    $table->dateTime('final_submitted_at')->nullable();
                }
                if (! Schema::hasColumn('projects', 'final_submitted_by_user_id')) {
                    $table->unsignedBigInteger('final_submitted_by_user_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('project_deliverables')) {
            Schema::table('project_deliverables', function (Blueprint $table) {
                if (! Schema::hasColumn('project_deliverables', 'slot_key')) {
                    $table->string('slot_key', 32)->nullable()->index();
                }
            });
        }

        SchemaGuards::createTableIfMissing('project_member_verifications', function (Blueprint $table) {
            $table->id('project_member_verification_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->dateTime('verified_at');
            $table->timestamps();

            $table->unique(['project_id', 'user_id'], 'project_member_verifications_unique');
        });
    }

    public function down(): void
    {
        // Expand-only: leave columns and the verification table in place.
    }
};

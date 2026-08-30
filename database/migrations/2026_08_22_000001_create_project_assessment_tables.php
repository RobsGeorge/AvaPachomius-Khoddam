<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Team project assessments linked to a course module (exam-style).
 * Additive only. church_id on every table (BelongsToChurch).
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('project_assessments', function (Blueprint $table) {
            $table->id('project_assessment_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('module_id')->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('min_team_size')->default(2);
            $table->unsignedTinyInteger('max_team_size')->default(4);
            $table->boolean('is_published')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->index(['church_id', 'course_id', 'is_published'], 'project_assessments_church_course_pub');
        });

        SchemaGuards::createTableIfMissing('projects', function (Blueprint $table) {
            $table->id('project_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->string('title', 255);
            $table->text('requirements')->nullable();
            $table->string('status', 16)->default('open');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_assessment_id', 'status'], 'projects_assessment_status');
        });

        SchemaGuards::createTableIfMissing('project_phases', function (Blueprint $table) {
            $table->id('project_phase_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->dateTime('deadline')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('project_deliverables', function (Blueprint $table) {
            $table->id('project_deliverable_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('project_memberships', function (Blueprint $table) {
            $table->id('project_membership_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 16)->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id'], 'project_memberships_project_user_unique');
            $table->index(['project_assessment_id', 'user_id', 'status'], 'project_memberships_assessment_user_status');
        });

        SchemaGuards::createTableIfMissing('project_change_requests', function (Blueprint $table) {
            $table->id('project_change_request_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('from_project_id')->index();
            $table->text('reason');
            $table->string('status', 16)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['project_assessment_id', 'status'], 'project_change_requests_assessment_status');
        });

        $this->seedProjectsCapabilityForExistingChurches();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_change_requests');
        Schema::dropIfExists('project_memberships');
        Schema::dropIfExists('project_deliverables');
        Schema::dropIfExists('project_phases');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_assessments');
    }

    private function seedProjectsCapabilityForExistingChurches(): void
    {
        if (! Schema::hasTable('church') || ! Schema::hasTable('church_capability')) {
            return;
        }

        $churchIds = DB::table('church')->pluck('church_id');
        foreach ($churchIds as $churchId) {
            $exists = DB::table('church_capability')
                ->where('church_id', $churchId)
                ->where('capability_key', 'projects')
                ->exists();

            if (! $exists) {
                DB::table('church_capability')->insert([
                    'church_id' => $churchId,
                    'capability_key' => 'projects',
                    'enabled' => true,
                ]);
            }
        }
    }
};

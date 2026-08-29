<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects v2 — typed deliverables plus one team submission (with files) per
 * deliverable. Additive only; church_id on every new table (BelongsToChurch).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_deliverables')) {
            Schema::table('project_deliverables', function (Blueprint $table) {
                if (! Schema::hasColumn('project_deliverables', 'submission_type')) {
                    $table->string('submission_type', 16)->default('pdf');
                }
                if (! Schema::hasColumn('project_deliverables', 'file_mode')) {
                    $table->string('file_mode', 8)->default('single');
                }
                if (! Schema::hasColumn('project_deliverables', 'is_required')) {
                    $table->boolean('is_required')->default(true);
                }
                if (! Schema::hasColumn('project_deliverables', 'allow_late')) {
                    $table->boolean('allow_late')->default(true);
                }
                if (! Schema::hasColumn('project_deliverables', 'instructions')) {
                    $table->text('instructions')->nullable();
                }
            });
        }

        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (! Schema::hasColumn('projects', 'team_workspace_url')) {
                    $table->string('team_workspace_url', 2048)->nullable();
                }
                if (! Schema::hasColumn('projects', 'team_announcement')) {
                    $table->text('team_announcement')->nullable();
                }
            });
        }

        SchemaGuards::createTableIfMissing('project_deliverable_submissions', function (Blueprint $table) {
            $table->id('project_deliverable_submission_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_assessment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('project_deliverable_id')->index();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->index();
            $table->text('body')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamps();

            $table->unique(
                ['project_id', 'project_deliverable_id'],
                'project_deliverable_submissions_team_unique'
            );
        });

        SchemaGuards::createTableIfMissing('project_submission_files', function (Blueprint $table) {
            $table->id('project_submission_file_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('project_deliverable_submission_id')->index();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->index();
            $table->string('file_path', 2048);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_submission_files');
        Schema::dropIfExists('project_deliverable_submissions');
    }
};

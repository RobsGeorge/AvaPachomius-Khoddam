<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addStringColumn('course', 'status', 20, false, 'min_attendance_percentage');
        if (Schema::hasTable('course') && Schema::hasColumn('course', 'status')) {
            \Illuminate\Support\Facades\DB::table('course')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update(['status' => 'active']);
        }

        MigrationSupport::addColumn('course', 'grading_locked_at', function (Blueprint $table) {
            $table->timestamp('grading_locked_at')->nullable()->after('status');
        });
        MigrationSupport::addColumn('course', 'grades_announced_at', function (Blueprint $table) {
            $table->timestamp('grades_announced_at')->nullable()->after('grading_locked_at');
        });
        MigrationSupport::addColumn('course', 'closed_at', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('grades_announced_at');
        });
        MigrationSupport::addColumn('course', 'closed_by_user_id', function (Blueprint $table) {
            $table->unsignedBigInteger('closed_by_user_id')->nullable()->after('closed_at');
            if (Schema::hasTable('user')) {
                $table->foreign('closed_by_user_id')->references('user_id')->on('user')->nullOnDelete();
            }
        });
        MigrationSupport::addBooleanColumn('course', 'grace_marks_enabled', false, 'closed_by_user_id');
        MigrationSupport::addColumn('course', 'max_grace_marks', function (Blueprint $table) {
            $table->decimal('max_grace_marks', 5, 2)->default(0)->after('grace_marks_enabled');
        });
        MigrationSupport::addStringColumn('course', 'grace_eligibility_mode', 20, false, 'max_grace_marks');

        MigrationSupport::addBooleanColumn('user_course_role', 'eligible_for_grace', false);
        MigrationSupport::addColumn('user_course_role', 'pending_grace_marks', function (Blueprint $table) {
            $table->decimal('pending_grace_marks', 5, 2)->nullable();
        });
        MigrationSupport::addColumn('user_course_role', 'staff_archived_at', function (Blueprint $table) {
            $table->timestamp('staff_archived_at')->nullable();
        });

        Schema::create('course_graduations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('announced_by_user_id')->nullable();
            $table->timestamp('announced_at');
            $table->string('status', 20)->default('final');
            $table->decimal('passing_percentage', 5, 2)->nullable();
            $table->decimal('min_attendance_percentage', 5, 2)->nullable();
            $table->decimal('max_grace_marks', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('course_id')->references('course_id')->on('course')->cascadeOnDelete();
            $table->foreign('announced_by_user_id')->references('user_id')->on('user')->nullOnDelete();
            $table->index(['course_id', 'announced_at']);
        });

        Schema::create('course_graduation_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_graduation_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('raw_total_grade', 6, 2)->default(0);
            $table->decimal('grace_marks_applied', 5, 2)->default(0);
            $table->decimal('final_total_grade', 6, 2)->default(0);
            $table->decimal('attendance_pct', 5, 2)->default(0);
            $table->string('letter_grade', 10)->default('F');
            $table->boolean('eligible')->default(false);
            $table->boolean('graduated')->default(false);
            $table->string('failure_reason', 20)->nullable();
            $table->json('grades_detail_json')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->foreign('course_graduation_id')->references('id')->on('course_graduations')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->unique(['course_graduation_id', 'user_id'], 'course_grad_students_unique');
        });

        Schema::create('course_certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('locale', 5)->default('en');
            $table->string('name', 150)->default('Default');
            $table->longText('body_html');
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->foreign('course_id')->references('course_id')->on('course')->cascadeOnDelete();
            $table->unique(['course_id', 'locale'], 'course_cert_tpl_course_locale');
        });

        Schema::create('course_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_graduation_student_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->uuid('certificate_uuid')->unique();
            $table->timestamp('issued_at');
            $table->string('pdf_path', 500)->nullable();
            $table->timestamps();

            $table->foreign('course_graduation_student_id')->references('id')->on('course_graduation_students')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('user')->cascadeOnDelete();
            $table->foreign('course_id')->references('course_id')->on('course')->cascadeOnDelete();
        });

        Schema::create('course_graduation_email_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('template_key', 60);
            $table->string('locale', 5)->default('en');
            $table->string('subject', 255);
            $table->longText('body_html');
            $table->timestamps();

            $table->foreign('course_id')->references('course_id')->on('course')->cascadeOnDelete();
            $table->unique(['course_id', 'template_key', 'locale'], 'course_grad_email_tpl_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_graduation_email_templates');
        Schema::dropIfExists('course_certificates');
        Schema::dropIfExists('course_certificate_templates');
        Schema::dropIfExists('course_graduation_students');
        Schema::dropIfExists('course_graduations');

        foreach ([
            'staff_archived_at',
            'pending_grace_marks',
            'eligible_for_grace',
        ] as $column) {
            if (Schema::hasTable('user_course_role') && Schema::hasColumn('user_course_role', $column)) {
                Schema::table('user_course_role', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasTable('course')) {
            if (Schema::hasColumn('course', 'closed_by_user_id')) {
                Schema::table('course', function (Blueprint $table) {
                    if (MigrationSupport::foreignKeyExists('course', 'course_closed_by_user_id_foreign')) {
                        $table->dropForeign(['closed_by_user_id']);
                    }
                    $table->dropColumn('closed_by_user_id');
                });
            }

            foreach ([
                'grace_eligibility_mode',
                'max_grace_marks',
                'grace_marks_enabled',
                'closed_at',
                'grades_announced_at',
                'grading_locked_at',
                'status',
            ] as $column) {
                if (Schema::hasColumn('course', $column)) {
                    Schema::table('course', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};

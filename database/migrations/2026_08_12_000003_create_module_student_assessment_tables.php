<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMA1: private post-module student assessments + anonymous instructor notes.
 * Additive only. Scores are never student-facing.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('assessment_criteria', function (Blueprint $table) {
            $table->id('criterion_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->string('key', 64);
            $table->string('label_en', 200);
            $table->string('label_ar', 200);
            $table->unsignedSmallInteger('weight')->default(0);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['church_id', 'key'], 'assessment_criteria_church_key_unique');
            $table->index(['church_id', 'is_active', 'order_index'], 'assessment_criteria_church_active_order');
        });

        SchemaGuards::createTableIfMissing('module_student_assessments', function (Blueprint $table) {
            $table->id('assessment_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('module_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedSmallInteger('total_score')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('assessed_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['church_id', 'course_id', 'module_id', 'user_id'],
                'module_student_assessments_unique'
            );
            $table->index(['course_id', 'module_id'], 'module_student_assessments_course_module');
        });

        SchemaGuards::createTableIfMissing('module_student_assessment_scores', function (Blueprint $table) {
            $table->id('score_id');
            $table->unsignedBigInteger('assessment_id')->index();
            $table->unsignedBigInteger('criterion_id')->index();
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['assessment_id', 'criterion_id'], 'module_student_assessment_scores_unique');
        });

        SchemaGuards::createTableIfMissing('student_instructor_notes', function (Blueprint $table) {
            $table->id('note_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('subject_user_id')->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->unsignedBigInteger('module_id')->nullable()->index();
            $table->text('body');
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->timestamps();

            $table->index(['church_id', 'subject_user_id', 'created_at'], 'student_instructor_notes_subject_timeline');
        });

        $this->seedDefaultCriteriaForExistingChurches();
    }

    public function down(): void
    {
        Schema::dropIfExists('student_instructor_notes');
        Schema::dropIfExists('module_student_assessment_scores');
        Schema::dropIfExists('module_student_assessments');
        Schema::dropIfExists('assessment_criteria');
    }

    private function seedDefaultCriteriaForExistingChurches(): void
    {
        if (! Schema::hasTable('church') || ! Schema::hasTable('assessment_criteria')) {
            return;
        }

        $defaults = [
            ['key' => 'interaction', 'label_en' => 'Classroom / session interaction', 'label_ar' => 'التفاعل في الصف / الجلسة', 'weight' => 20, 'order_index' => 1],
            ['key' => 'willingness_to_help', 'label_en' => 'Willingness to help others', 'label_ar' => 'الاستعداد لمساعدة الآخرين', 'weight' => 15, 'order_index' => 2],
            ['key' => 'eagerness_to_learn', 'label_en' => 'Eagerness to learn', 'label_ar' => 'الحماس للتعلّم', 'weight' => 20, 'order_index' => 3],
            ['key' => 'collaboration', 'label_en' => 'Collaboration with colleagues', 'label_ar' => 'التعاون مع الزملاء', 'weight' => 15, 'order_index' => 4],
            ['key' => 'responsibility', 'label_en' => 'Responsibility / follow-through', 'label_ar' => 'المسؤولية والمتابعة', 'weight' => 15, 'order_index' => 5],
            ['key' => 'respect_conduct', 'label_en' => 'Respect & conduct', 'label_ar' => 'الاحترام والسلوك', 'weight' => 15, 'order_index' => 6],
        ];

        $churchIds = DB::table('church')->pluck('church_id');
        $now = now();

        foreach ($churchIds as $churchId) {
            foreach ($defaults as $row) {
                $exists = DB::table('assessment_criteria')
                    ->where('church_id', $churchId)
                    ->where('key', $row['key'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('assessment_criteria')->insert([
                    'church_id' => $churchId,
                    'key' => $row['key'],
                    'label_en' => $row['label_en'],
                    'label_ar' => $row['label_ar'],
                    'weight' => $row['weight'],
                    'order_index' => $row['order_index'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};

<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exams')) {
            MigrationSupport::addColumn('exams', 'exam_type', function (Blueprint $table) {
                $table->string('exam_type', 20)->default('exam')->after('exam_name');
            });
            MigrationSupport::addColumn('exams', 'delivery_mode', function (Blueprint $table) {
                $table->string('delivery_mode', 20)->default('offline')->after('exam_type');
            });
            MigrationSupport::addColumn('exams', 'is_published', function (Blueprint $table) {
                $table->boolean('is_published')->default(false)->after('passing_score');
            });
            MigrationSupport::addColumn('exams', 'total_points', function (Blueprint $table) {
                $table->decimal('total_points', 8, 2)->default(0)->after('is_published');
            });
            MigrationSupport::addColumn('exams', 'shuffle_questions', function (Blueprint $table) {
                $table->boolean('shuffle_questions')->default(false)->after('total_points');
            });
            MigrationSupport::addColumn('exams', 'allow_late_entry', function (Blueprint $table) {
                $table->boolean('allow_late_entry')->default(true)->after('shuffle_questions');
            });
        }

        if (Schema::hasTable('exam_results')) {
            MigrationSupport::addColumn('exam_results', 'status', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->after('score');
            });
            MigrationSupport::addColumn('exam_results', 'auto_score', function (Blueprint $table) {
                $table->decimal('auto_score', 8, 2)->nullable()->after('status');
            });
            MigrationSupport::addColumn('exam_results', 'manual_score', function (Blueprint $table) {
                $table->decimal('manual_score', 8, 2)->nullable()->after('auto_score');
            });
            MigrationSupport::addColumn('exam_results', 'submitted_at', function (Blueprint $table) {
                $table->timestamp('submitted_at')->nullable()->after('manual_score');
            });
            MigrationSupport::addColumn('exam_results', 'attempt_id', function (Blueprint $table) {
                $table->unsignedBigInteger('attempt_id')->nullable()->after('schedule_id');
            });
        }

        SchemaGuards::createTableIfMissing('exam_questions', function (Blueprint $table) {
            $table->id('question_id');
            $table->foreignId('exam_id')->constrained('exams', 'exam_id')->cascadeOnDelete();
            $table->string('question_type', 20);
            $table->text('prompt');
            $table->decimal('points', 8, 2)->default(1);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->text('essay_ai_prompt')->nullable();
            $table->text('essay_keywords')->nullable();
            $table->text('essay_rubric')->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('exam_question_options', function (Blueprint $table) {
            $table->id('option_id');
            $table->foreignId('question_id')->constrained('exam_questions', 'question_id')->cascadeOnDelete();
            $table->string('label', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('exam_attempts', function (Blueprint $table) {
            $table->id('attempt_id');
            $table->foreignId('exam_id')->constrained('exams', 'exam_id')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('exam_schedules', 'schedule_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user', 'user_id')->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->json('answers_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['schedule_id', 'user_id']);
        });

        SchemaGuards::createTableIfMissing('exam_answers', function (Blueprint $table) {
            $table->id('answer_id');
            $table->foreignId('attempt_id')->constrained('exam_attempts', 'attempt_id')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('exam_questions', 'question_id')->cascadeOnDelete();
            $table->unsignedBigInteger('selected_option_id')->nullable();
            $table->text('text_answer')->nullable();
            $table->decimal('auto_score', 8, 2)->nullable();
            $table->decimal('manual_score', 8, 2)->nullable();
            $table->text('ai_feedback')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_question_options');
        Schema::dropIfExists('exam_questions');

        foreach (['exams' => ['allow_late_entry', 'shuffle_questions', 'total_points', 'is_published', 'delivery_mode', 'exam_type']] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $drop = array_filter($columns, fn ($c) => Schema::hasColumn($table, $c));
            if ($drop !== []) {
                Schema::table($table, function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }

        if (Schema::hasTable('exam_results')) {
            $cols = array_filter(
                ['attempt_id', 'submitted_at', 'manual_score', 'auto_score', 'status'],
                fn ($c) => Schema::hasColumn('exam_results', $c)
            );
            if ($cols !== []) {
                Schema::table('exam_results', function (Blueprint $table) use ($cols) {
                    $table->dropColumn($cols);
                });
            }
        }
    }
};

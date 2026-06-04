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
        if (Schema::hasTable('exam_attempts')) {
            MigrationSupport::addColumn('exam_attempts', 'checklist_acknowledged_at', function (Blueprint $table) {
                $table->timestamp('checklist_acknowledged_at')->nullable()->after('started_at');
            });
            MigrationSupport::addColumn('exam_attempts', 'proctor_warnings', function (Blueprint $table) {
                $table->unsignedTinyInteger('proctor_warnings')->default(0)->after('submitted_at');
            });
            MigrationSupport::addColumn('exam_attempts', 'terminated_for_cheating', function (Blueprint $table) {
                $table->boolean('terminated_for_cheating')->default(false)->after('proctor_warnings');
            });
            MigrationSupport::addColumn('exam_attempts', 'terminated_at', function (Blueprint $table) {
                $table->timestamp('terminated_at')->nullable()->after('terminated_for_cheating');
            });
        }

        SchemaGuards::createTableIfMissing('exam_proctor_events', function (Blueprint $table) {
            $table->id('event_id');
            $table->foreignId('attempt_id')->constrained('exam_attempts', 'attempt_id')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams', 'exam_id')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('exam_schedules', 'schedule_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user', 'user_id')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->unsignedTinyInteger('warning_number')->default(1);
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_proctor_events');

        if (! Schema::hasTable('exam_attempts')) {
            return;
        }

        foreach (['terminated_at', 'terminated_for_cheating', 'proctor_warnings', 'checklist_acknowledged_at'] as $col) {
            if (Schema::hasColumn('exam_attempts', $col)) {
                Schema::table('exam_attempts', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};

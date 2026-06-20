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
        if (Schema::hasTable('course_module')) {
            MigrationSupport::addDateColumn('course_module', 'start_date', true, 'module_id');
            MigrationSupport::addDateColumn('course_module', 'end_date', true, 'start_date');
            MigrationSupport::addColumn('course_module', 'order_index', function (Blueprint $table) {
                $table->unsignedSmallInteger('order_index')->default(0)->after('end_date');
            });
            MigrationSupport::addColumn('course_module', 'status', function (Blueprint $table) {
                $table->string('status', 20)->default('draft')->after('order_index');
            });
            MigrationSupport::addBooleanColumn('course_module', 'feedback_open', false, 'status');
            MigrationSupport::addColumn('course_module', 'ended_at', function (Blueprint $table) {
                $table->timestamp('ended_at')->nullable()->after('feedback_open');
            });
            MigrationSupport::addColumn('course_module', 'ended_by_user_id', function (Blueprint $table) {
                $table->unsignedBigInteger('ended_by_user_id')->nullable()->after('ended_at');
            });

            if (Schema::hasColumn('course_module', 'ended_by_user_id')
                && Schema::hasTable('user')
                && ! MigrationSupport::foreignKeyExists('course_module', 'course_module_ended_by_user_id_foreign')) {
                Schema::table('course_module', function (Blueprint $table) {
                    $table->foreign('ended_by_user_id', 'course_module_ended_by_user_id_foreign')
                        ->references('user_id')->on('user')->nullOnDelete();
                });
            }
        }

        SchemaGuards::createTableIfMissing('module_session', function (Blueprint $table) {
            $table->id('module_session_id');
            $table->foreignId('module_id')->constrained('modules', 'module_id')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('session', 'session_id')->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number')->nullable();
            $table->unique(['module_id', 'session_id']);
        });

        SchemaGuards::createTableIfMissing('module_feedback', function (Blueprint $table) {
            $table->id('feedback_id');
            $table->foreignId('user_id')->constrained('user', 'user_id')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('course', 'course_id')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules', 'module_id')->cascadeOnDelete();
            $table->unsignedTinyInteger('lecture_rating')->nullable();
            $table->text('lecture_comments')->nullable();
            $table->unsignedTinyInteger('speaker_rating')->nullable();
            $table->text('speaker_comments')->nullable();
            $table->unsignedTinyInteger('workshop_rating')->nullable();
            $table->text('workshop_comments')->nullable();
            $table->unsignedTinyInteger('timing_rating')->nullable();
            $table->text('timing_comments')->nullable();
            $table->unsignedTinyInteger('content_rating')->nullable();
            $table->text('content_comments')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_feedback');
        Schema::dropIfExists('module_session');

        if (Schema::hasTable('course_module')) {
            Schema::table('course_module', function (Blueprint $table) {
                if (MigrationSupport::foreignKeyExists('course_module', 'course_module_ended_by_user_id_foreign')) {
                    $table->dropForeign('course_module_ended_by_user_id_foreign');
                }
            });

            $columns = [
                'start_date', 'end_date', 'order_index', 'status',
                'feedback_open', 'ended_at', 'ended_by_user_id',
            ];
            $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('course_module', $col));

            if ($toDrop !== []) {
                Schema::table('course_module', function (Blueprint $table) use ($toDrop) {
                    $table->dropColumn($toDrop);
                });
            }
        }
    }

};

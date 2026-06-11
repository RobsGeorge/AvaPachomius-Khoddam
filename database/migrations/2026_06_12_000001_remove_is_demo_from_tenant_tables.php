<?php

use App\Models\Assignment;
use App\Models\Content;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user', 'is_demo')) {
            return;
        }

        $demoUserIds = User::where('is_demo', true)->pluck('user_id');
        $demoCourseIds = Course::where('is_demo', true)->pluck('course_id');
        $demoModuleIds = Module::where('is_demo', true)->pluck('module_id');
        $demoContentIds = Content::where('is_demo', true)->pluck('content_id');
        $demoAssignmentIds = Schema::hasTable('assignments')
            ? Assignment::where('is_demo', true)->pluck('assignment_id')
            : collect();

        DB::transaction(function () use (
            $demoUserIds,
            $demoCourseIds,
            $demoModuleIds,
            $demoContentIds,
            $demoAssignmentIds
        ) {
            if ($demoUserIds->isNotEmpty()) {
                UserCourseRole::whereIn('user_id', $demoUserIds)->delete();

                foreach (['module_feedback', 'content_feedback', 'assignment_submission', 'student_grades', 'attendance', 'exam_attempts', 'exam_results'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn('user_id', $demoUserIds)->delete();
                    }
                }
            }

            if ($demoCourseIds->isNotEmpty()) {
                foreach ($demoCourseIds as $courseId) {
                    $this->deleteDemoCourse($courseId);
                }
            }

            $this->deleteDemoModuleDependencies($demoModuleIds);
            $this->deleteDemoContentDependencies($demoContentIds, $demoModuleIds);

            if ($demoAssignmentIds->isNotEmpty() && Schema::hasTable('assignments')) {
                Assignment::whereIn('assignment_id', $demoAssignmentIds)->delete();
            }

            if ($demoContentIds->isNotEmpty() && Schema::hasTable('content')) {
                Content::whereIn('content_id', $demoContentIds)->delete();
            }

            if ($demoModuleIds->isNotEmpty() && Schema::hasTable('modules')) {
                Module::whereIn('module_id', $demoModuleIds)->delete();
            }

            if ($demoUserIds->isNotEmpty()) {
                User::whereIn('user_id', $demoUserIds)->delete();
            }
        });

        foreach (['user', 'course', 'modules', 'content', 'assignments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_demo')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('is_demo');
                });
            }
        }
    }

    private function deleteDemoCourse(int $courseId): void
    {
        $sessionIds = DB::table('session')->where('course_id', $courseId)->pluck('session_id');
        $examIds = DB::table('exams')->where('course_id', $courseId)->pluck('exam_id');
        $moduleIds = DB::table('course_module')->where('course_id', $courseId)->pluck('module_id');

        if (Schema::hasTable('lectures') && $sessionIds->isNotEmpty()) {
            $this->deleteLecturesByIds(
                DB::table('lectures')->whereIn('session_id', $sessionIds)->pluck('lecture_id')
            );
        }

        if (Schema::hasTable('lectures') && $moduleIds->isNotEmpty()) {
            $this->deleteLecturesByIds(
                DB::table('lectures')->whereIn('module_id', $moduleIds)->whereNull('session_id')->pluck('lecture_id')
            );
        }

        if ($sessionIds->isNotEmpty() && Schema::hasTable('module_session')) {
            DB::table('module_session')->whereIn('session_id', $sessionIds)->delete();
        }

        if ($sessionIds->isNotEmpty()) {
            DB::table('session')->whereIn('session_id', $sessionIds)->delete();
        }

        if ($examIds->isNotEmpty()) {
            DB::table('exams')->whereIn('exam_id', $examIds)->delete();
        }

        if (Schema::hasTable('grade_categories')) {
            DB::table('grade_categories')->where('course_id', $courseId)->delete();
        }

        if (Schema::hasTable('module_feedback')) {
            DB::table('module_feedback')->where('course_id', $courseId)->delete();
        }

        if (Schema::hasTable('course_module')) {
            DB::table('course_module')->where('course_id', $courseId)->delete();
        }

        DB::table('course')->where('course_id', $courseId)->delete();
    }

    private function deleteDemoModuleDependencies($demoModuleIds): void
    {
        if ($demoModuleIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('course_module')) {
            DB::table('course_module')->whereIn('module_id', $demoModuleIds)->delete();
        }

        if (Schema::hasTable('module_session')) {
            DB::table('module_session')->whereIn('module_id', $demoModuleIds)->delete();
        }

        if (Schema::hasTable('module_feedback')) {
            DB::table('module_feedback')->whereIn('module_id', $demoModuleIds)->delete();
        }

        if (Schema::hasTable('lectures')) {
            $this->deleteLecturesByIds(
                DB::table('lectures')->whereIn('module_id', $demoModuleIds)->pluck('lecture_id')
            );
        }

        if (Schema::hasTable('exams')) {
            DB::table('exams')->whereIn('module_id', $demoModuleIds)->delete();
        }

        if (Schema::hasTable('session') && Schema::hasColumn('session', 'module_id')) {
            $sessionIds = DB::table('session')->whereIn('module_id', $demoModuleIds)->pluck('session_id');

            if ($sessionIds->isNotEmpty() && Schema::hasTable('attendance')) {
                DB::table('attendance')->whereIn('session_id', $sessionIds)->delete();
            }

            if ($sessionIds->isNotEmpty() && Schema::hasTable('module_session')) {
                DB::table('module_session')->whereIn('session_id', $sessionIds)->delete();
            }

            if ($sessionIds->isNotEmpty()) {
                DB::table('session')->whereIn('session_id', $sessionIds)->delete();
            }
        }
    }

    private function deleteDemoContentDependencies($demoContentIds, $demoModuleIds): void
    {
        if (! Schema::hasTable('module_content')) {
            return;
        }

        if ($demoContentIds->isNotEmpty()) {
            DB::table('module_content')->whereIn('content_id', $demoContentIds)->delete();
        }

        if ($demoModuleIds->isNotEmpty()) {
            DB::table('module_content')->whereIn('module_id', $demoModuleIds)->delete();
        }
    }

    private function deleteLecturesByIds($lectureIds): void
    {
        if ($lectureIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('lecture_materials')) {
            DB::table('lecture_materials')->whereIn('lecture_id', $lectureIds)->delete();
        }

        DB::table('lectures')->whereIn('lecture_id', $lectureIds)->delete();
    }

    public function down(): void
    {
        // Demo feature removed; columns are not restored.
    }
};

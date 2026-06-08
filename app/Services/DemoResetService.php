<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Content;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoResetService
{
    public function wipeDemoData(): void
    {
        $demoUserIds = User::where('is_demo', true)->pluck('user_id');
        $demoCourseIds = Course::where('is_demo', true)->pluck('course_id');

        if ($demoUserIds->isEmpty() && $demoCourseIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($demoUserIds, $demoCourseIds) {
            if ($demoUserIds->isNotEmpty()) {
                UserCourseRole::whereIn('user_id', $demoUserIds)->delete();

                if (Schema::hasTable('module_feedback')) {
                    DB::table('module_feedback')->whereIn('user_id', $demoUserIds)->delete();
                }
                if (Schema::hasTable('content_feedback')) {
                    DB::table('content_feedback')->whereIn('user_id', $demoUserIds)->delete();
                }
                if (Schema::hasTable('assignment_submission')) {
                    DB::table('assignment_submission')->whereIn('user_id', $demoUserIds)->delete();
                }
                if (Schema::hasTable('student_grades')) {
                    DB::table('student_grades')->whereIn('user_id', $demoUserIds)->delete();
                }
                if (Schema::hasTable('attendance')) {
                    DB::table('attendance')->whereIn('user_id', $demoUserIds)->delete();
                }
                if (Schema::hasTable('exam_attempts')) {
                    DB::table('exam_attempts')->whereIn('user_id', $demoUserIds)->delete();
                }
                if (Schema::hasTable('exam_results')) {
                    DB::table('exam_results')->whereIn('user_id', $demoUserIds)->delete();
                }
            }

            if ($demoCourseIds->isNotEmpty()) {
                foreach ($demoCourseIds as $courseId) {
                    $sessionIds = DB::table('session')->where('course_id', $courseId)->pluck('session_id');
                    $examIds = DB::table('exams')->where('course_id', $courseId)->pluck('exam_id');
                    $moduleIds = DB::table('course_module')->where('course_id', $courseId)->pluck('module_id');

                    if (Schema::hasTable('lectures') && $sessionIds->isNotEmpty()) {
                        DB::table('lectures')->whereIn('session_id', $sessionIds)->delete();
                    }
                    if (Schema::hasTable('lectures') && $moduleIds->isNotEmpty()) {
                        DB::table('lectures')->whereIn('module_id', $moduleIds)->whereNull('session_id')->delete();
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
                    if (Schema::hasTable('course_module')) {
                        DB::table('course_module')->where('course_id', $courseId)->delete();
                    }
                    DB::table('course')->where('course_id', $courseId)->delete();
                }
            }

            if (Schema::hasTable('assignments')) {
                Assignment::where('is_demo', true)->delete();
            }
            if (Schema::hasTable('content')) {
                Content::where('is_demo', true)->delete();
            }
            if (Schema::hasTable('modules')) {
                Module::where('is_demo', true)->delete();
            }

            User::where('is_demo', true)->delete();
        });
    }
}

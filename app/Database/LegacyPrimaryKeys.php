<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LegacyPrimaryKeys
{
    /**
     * Tables that use a custom primary key column instead of Laravel's default `id`.
     *
     * @var array<string, string>
     */
    public const PRIMARY_KEYS = [
        'user' => 'user_id',
        'roles' => 'role_id',
        'course' => 'course_id',
        'user_course_role' => 'user_course_role_id',
        'session' => 'session_id',
        'attendance' => 'attendance_id',
        'assessment' => 'assessment_id',
        'course_assessment' => 'course_assessment_id',
        'user_assessment' => 'user_assessment_id',
        'modules' => 'module_id',
        'course_module' => 'course_module_id',
        'content' => 'content_id',
        'module_content' => 'module_content_id',
        'assignments' => 'assignment_id',
        'assignment_submission' => 'submission_id',
        'exams' => 'exam_id',
        'exam_schedules' => 'schedule_id',
        'exam_results' => 'result_id',
        'content_feedback' => 'feedback_id',
        'lectures' => 'lecture_id',
        'lecture_materials' => 'material_id',
        'grade_categories' => 'category_id',
        'grade_items' => 'item_id',
        'student_grades' => 'grade_id',
    ];

    public static function normalizeAll(): void
    {
        foreach (self::PRIMARY_KEYS as $table => $primaryKey) {
            self::normalize($table, $primaryKey);
        }
    }

    public static function normalizeFor(string $table): void
    {
        if (! isset(self::PRIMARY_KEYS[$table])) {
            return;
        }

        self::normalize($table, self::PRIMARY_KEYS[$table]);
    }

    /**
     * Legacy databases created the PK as `id`; the app expects custom names (e.g. user_id).
     */
    public static function normalize(string $table, string $targetColumn): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, $targetColumn)) {
            return;
        }

        if (! Schema::hasColumn($table, 'id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $quotedTable = '`'.str_replace('`', '``', $table).'`';
            $quotedTarget = '`'.str_replace('`', '``', $targetColumn).'`';

            try {
                DB::statement("ALTER TABLE {$quotedTable} RENAME COLUMN `id` TO {$quotedTarget}");
            } catch (\Throwable) {
                DB::statement("ALTER TABLE {$quotedTable} CHANGE `id` {$quotedTarget} BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
            }

            return;
        }

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table($table, function ($blueprint) use ($targetColumn) {
            $blueprint->renameColumn('id', $targetColumn);
        });
    }
}

<?php

namespace App\Database;

use Illuminate\Support\Facades\Schema;

/** Pivot columns that exist on legacy `course_module` (varies by migration state). */
final class CourseModulePivot
{
    private static ?array $columns = null;

    public static function columns(): array
    {
        if (self::$columns !== null) {
            return self::$columns;
        }

        if (! Schema::hasTable('course_module')) {
            return self::$columns = [];
        }

        $possible = [
            'start_date',
            'end_date',
            'order_index',
            'status',
            'feedback_open',
            'ended_at',
            'ended_by_user_id',
        ];

        return self::$columns = array_values(array_filter(
            $possible,
            fn (string $column) => Schema::hasColumn('course_module', $column)
        ));
    }

    public static function hasOrderIndex(): bool
    {
        return in_array('order_index', self::columns(), true);
    }
}

<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;

class AttendancePolicy extends Model
{
    use BelongsToChurch;

    protected $table = 'attendance_policy';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'late_threshold_minutes',
        'late_grade_percentage',
        'is_enabled',
    ];

    protected $casts = [
        'late_threshold_minutes' => 'integer',
        'late_grade_percentage' => 'float',
        'is_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'late_threshold_minutes' => (int) config('attendance.late_threshold_minutes', 15),
            'late_grade_percentage' => (float) config('attendance.late_grade_percentage', 50),
            'is_enabled' => true,
        ]);
    }
}

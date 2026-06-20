<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendancePolicy extends Model
{
    protected $table = 'attendance_policy';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'late_threshold_minutes',
        'late_grade_percentage',
        'default_session_start_time',
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
            'default_session_start_time' => config('attendance.default_session_start_time', '09:00:00'),
            'is_enabled' => true,
        ]);
    }

    public function formattedDefaultStartTime(): string
    {
        $time = $this->default_session_start_time;

        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        return substr((string) $time, 0, 5);
    }
}

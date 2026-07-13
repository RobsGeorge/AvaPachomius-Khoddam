<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;
use App\Models\Attendance;
use App\Models\User;

class Session extends Model
{
    /** Legacy `session` table may not have timestamps on production. */
    public $timestamps = false;

    protected $table = 'session';

    protected $primaryKey = 'session_id';

    protected $fillable = [
        'course_id',
        'module_id',
        'week_number',
        'session_title',
        'session_date',
        'session_start_time',
        'notify_students',
        'attendance_closed_at',
        'attendance_closed_by_id',
    ];

    protected $casts = [
        'session_date' => 'date',
        'week_number'  => 'integer',
        'notify_students' => 'boolean',
        'attendance_closed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function shouldNotifyStudents(): bool
    {
        return (bool) ($this->notify_students ?? true);
    }

    public function isAttendanceClosed(): bool
    {
        return $this->attendance_closed_at !== null;
    }

    public function attendanceClosedBy()
    {
        return $this->belongsTo(User::class, 'attendance_closed_by_id', 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'session_id', 'session_id');
    }

    public function lectures()
    {
        return $this->hasMany(Lecture::class, 'session_id', 'session_id')
            ->orderBy('order_index')
            ->orderBy('lecture_id');
    }

    public function modules()
    {
        return $this->belongsToMany(
            Module::class,
            'module_session',
            'session_id',
            'module_id',
            'session_id',
            'module_id'
        )->withPivot('week_number');
    }

    public function notificationTargets()
    {
        return $this->belongsToMany(
            User::class,
            'session_notification_targets',
            'session_id',
            'user_id',
            'session_id',
            'user_id'
        )->withTimestamps();
    }
}


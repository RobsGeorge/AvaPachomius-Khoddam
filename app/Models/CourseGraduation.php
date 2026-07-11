<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseGraduation extends Model
{
    public const STATUS_FINAL = 'final';

    protected $fillable = [
        'course_id',
        'announced_by_user_id',
        'announced_at',
        'status',
        'passing_percentage',
        'min_attendance_percentage',
        'max_grace_marks',
    ];

    protected $casts = [
        'announced_at'              => 'datetime',
        'passing_percentage'        => 'float',
        'min_attendance_percentage' => 'float',
        'max_grace_marks'           => 'float',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function announcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'announced_by_user_id', 'user_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(CourseGraduationStudent::class, 'course_graduation_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourseGraduationStudent extends Model
{
    protected $fillable = [
        'course_graduation_id',
        'user_id',
        'raw_total_grade',
        'grace_marks_applied',
        'final_total_grade',
        'attendance_pct',
        'letter_grade',
        'eligible',
        'graduated',
        'failure_reason',
        'grades_detail_json',
        'emailed_at',
    ];

    protected $casts = [
        'raw_total_grade'     => 'float',
        'grace_marks_applied' => 'float',
        'final_total_grade'   => 'float',
        'attendance_pct'      => 'float',
        'eligible'            => 'boolean',
        'graduated'           => 'boolean',
        'grades_detail_json'  => 'array',
        'emailed_at'          => 'datetime',
    ];

    public function graduation(): BelongsTo
    {
        return $this->belongsTo(CourseGraduation::class, 'course_graduation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(CourseCertificate::class, 'course_graduation_student_id');
    }
}

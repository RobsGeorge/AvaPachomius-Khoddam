<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $primaryKey = 'exam_id';

    protected $fillable = [
        'course_id',
        'module_id',
        'exam_name',
        'duration_minutes',
        'study_resources',
        'exam_description',
        'passing_score',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class, 'exam_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class, 'exam_id');
    }
} 
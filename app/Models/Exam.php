<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $primaryKey = 'exam_id';
    
    protected $fillable = [
        'exam_name',
        'duration_minutes',
        'study_resources',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class, 'exam_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class, 'exam_id');
    }
} 
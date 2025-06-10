<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSchedule extends Model
{
    protected $primaryKey = 'schedule_id';
    
    protected $fillable = [
        'exam_id',
        'scheduled_date',
        'is_completed',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'is_completed' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class, 'schedule_id');
    }
} 
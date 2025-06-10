<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    protected $primaryKey = 'result_id';
    
    protected $fillable = [
        'exam_id',
        'user_id',
        'schedule_id',
        'score',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'schedule_id');
    }
} 
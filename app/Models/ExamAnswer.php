<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    protected $primaryKey = 'answer_id';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_id',
        'text_answer',
        'auto_score',
        'manual_score',
        'ai_feedback',
        'graded_at',
    ];

    protected $casts = [
        'auto_score'   => 'decimal:2',
        'manual_score' => 'decimal:2',
        'graded_at'    => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id', 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'question_id', 'question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(ExamQuestionOption::class, 'selected_option_id', 'option_id');
    }

    public function effectiveScore(): ?float
    {
        if ($this->manual_score !== null) {
            return (float) $this->manual_score;
        }

        return $this->auto_score !== null ? (float) $this->auto_score : null;
    }
}

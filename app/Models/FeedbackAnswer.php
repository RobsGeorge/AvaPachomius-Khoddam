<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackAnswer extends Model
{
    protected $primaryKey = 'answer_id';

    protected $fillable = [
        'submission_id', 'question_id', 'value',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeedbackSubmission::class, 'submission_id', 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FeedbackQuestion::class, 'question_id', 'question_id');
    }

    public function displayValue(): string
    {
        if ($this->value === null || $this->value === '') {
            return '—';
        }

        return (string) $this->value;
    }
}

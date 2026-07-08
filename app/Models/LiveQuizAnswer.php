<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveQuizAnswer extends Model
{
    protected $primaryKey = 'answer_id';

    protected $fillable = [
        'session_id', 'question_id', 'participant_id', 'option_id',
        'is_correct', 'points_earned', 'answered_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'float',
        'answered_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveQuizSession::class, 'session_id', 'session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(LiveQuizQuestion::class, 'question_id', 'question_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(LiveQuizParticipant::class, 'participant_id', 'participant_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(LiveQuizOption::class, 'option_id', 'option_id');
    }
}

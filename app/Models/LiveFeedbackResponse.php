<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveFeedbackResponse extends Model
{
    protected $primaryKey = 'response_id';

    protected $fillable = [
        'session_id', 'user_id',
        'lecture_rating', 'lecture_comments',
        'speaker_rating', 'speaker_comments',
        'workshop_rating', 'workshop_comments',
        'timing_rating', 'timing_comments',
        'content_rating', 'content_comments',
        'notes', 'submitted', 'submitted_at',
    ];

    protected $casts = [
        'submitted' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveFeedbackSession::class, 'session_id', 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function ratingKeys(): array
    {
        return ['lecture', 'speaker', 'workshop', 'timing', 'content'];
    }
}

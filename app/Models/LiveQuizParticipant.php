<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveQuizParticipant extends Model
{
    protected $primaryKey = 'participant_id';

    protected $fillable = [
        'session_id', 'user_id', 'team_number', 'display_name', 'score', 'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveQuizSession::class, 'session_id', 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LiveQuizAnswer::class, 'participant_id', 'participant_id');
    }
}

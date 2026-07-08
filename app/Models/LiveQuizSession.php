<?php

namespace App\Models;

use App\Services\LiveJoinCodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveQuizSession extends Model
{
    public const STATUS_LOBBY = 'lobby';
    public const STATUS_QUESTION = 'question';
    public const STATUS_RESULTS = 'results';
    public const STATUS_ENDED = 'ended';

    protected $primaryKey = 'session_id';

    protected $fillable = [
        'live_quiz_id', 'host_user_id', 'join_code', 'status', 'mode', 'team_count',
        'current_question_index', 'question_started_at', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'question_started_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LiveQuizSession $session) {
            if (empty($session->join_code)) {
                $session->join_code = LiveJoinCodeService::generate();
            }
        });
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(LiveQuiz::class, 'live_quiz_id', 'live_quiz_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id', 'user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LiveQuizParticipant::class, 'session_id', 'session_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LiveQuizAnswer::class, 'session_id', 'session_id');
    }

    public function currentQuestion(): ?LiveQuizQuestion
    {
        if ($this->current_question_index === null) {
            return null;
        }

        return $this->quiz?->questions()
            ->where('order_index', $this->current_question_index)
            ->with('options')
            ->first();
    }

    public function isTeamMode(): bool
    {
        return $this->mode === LiveQuiz::MODE_TEAM;
    }

    public function channelName(): string
    {
        return 'live-quiz.'.$this->session_id;
    }

    public function getRouteKeyName(): string
    {
        return 'session_id';
    }
}

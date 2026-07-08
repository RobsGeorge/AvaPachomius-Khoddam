<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LiveQuiz extends Model
{
    public const MODE_INDIVIDUAL = 'individual';
    public const MODE_TEAM = 'team';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';

    protected $primaryKey = 'live_quiz_id';

    protected $fillable = [
        'course_id', 'title', 'created_by_user_id', 'mode', 'team_count', 'join_code', 'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (LiveQuiz $quiz) {
            if (empty($quiz->join_code)) {
                $quiz->join_code = self::generateJoinCode();
            }
        });
    }

    public static function generateJoinCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('join_code', $code)->exists());

        return $code;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(LiveQuizQuestion::class, 'live_quiz_id', 'live_quiz_id')
            ->orderBy('order_index');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LiveQuizSession::class, 'live_quiz_id', 'live_quiz_id');
    }

    public function isTeamMode(): bool
    {
        return $this->mode === self::MODE_TEAM;
    }

    public function getRouteKeyName(): string
    {
        return 'live_quiz_id';
    }
}

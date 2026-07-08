<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackQuestion extends Model
{
    public const TYPE_RATING = 'rating';
    public const TYPE_SLIDER = 'slider';
    public const TYPE_MCQ = 'mcq';
    public const TYPE_TEXT = 'text';

    public const SCOPE_GENERAL = 'general';
    public const SCOPE_SESSION = 'session';
    public const SCOPE_LECTURE = 'lecture';
    public const SCOPE_INSTRUCTOR = 'instructor';

    protected $primaryKey = 'question_id';

    protected $fillable = [
        'survey_id', 'question_type', 'scope', 'session_id', 'lecture_id',
        'target_user_id', 'label', 'help_text', 'order_index', 'is_required', 'config',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'config' => 'array',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(FeedbackSurvey::class, 'survey_id', 'survey_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'lecture_id', 'lecture_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id', 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackAnswer::class, 'question_id', 'question_id');
    }

    public function choices(): array
    {
        return $this->config['choices'] ?? [];
    }

    public function sliderMin(): int
    {
        return (int) ($this->config['min'] ?? 1);
    }

    public function sliderMax(): int
    {
        return (int) ($this->config['max'] ?? 10);
    }

    public function ratingMax(): int
    {
        return (int) ($this->config['max_rating'] ?? 5);
    }

    public function scopeLabel(): string
    {
        $parts = [$this->label];

        if ($this->scope === self::SCOPE_SESSION && $this->session) {
            $parts[] = '('.$this->session->session_title.')';
        }
        if ($this->scope === self::SCOPE_LECTURE && $this->lecture) {
            $parts[] = '('.$this->lecture->title.')';
        }
        if ($this->scope === self::SCOPE_INSTRUCTOR && $this->targetUser) {
            $parts[] = '('.$this->targetUser->displayName().')';
        }

        return implode(' ', $parts);
    }

    public function getRouteKeyName(): string
    {
        return 'question_id';
    }
}

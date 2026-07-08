<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveQuizQuestion extends Model
{
    public const TYPE_MCQ = 'mcq';
    public const TYPE_TRUE_FALSE = 'true_false';

    protected $primaryKey = 'question_id';

    protected $fillable = [
        'live_quiz_id', 'order_index', 'question_type', 'prompt_text',
        'prompt_image_path', 'time_limit_seconds', 'points',
    ];

    protected $casts = [
        'points' => 'float',
        'time_limit_seconds' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(LiveQuiz::class, 'live_quiz_id', 'live_quiz_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(LiveQuizOption::class, 'question_id', 'question_id')
            ->orderBy('order_index');
    }

    public function isAutoGradable(): bool
    {
        return in_array($this->question_type, [self::TYPE_MCQ, self::TYPE_TRUE_FALSE], true);
    }

    public function getRouteKeyName(): string
    {
        return 'question_id';
    }
}

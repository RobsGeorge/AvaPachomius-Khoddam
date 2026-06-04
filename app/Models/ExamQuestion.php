<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamQuestion extends Model
{
    public const TYPE_MCQ = 'mcq';

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_ESSAY = 'essay';

    protected $primaryKey = 'question_id';

    protected $fillable = [
        'exam_id',
        'question_type',
        'prompt',
        'points',
        'order_index',
        'essay_ai_prompt',
        'essay_keywords',
        'essay_rubric',
    ];

    protected $casts = [
        'points'      => 'decimal:2',
        'order_index' => 'integer',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ExamQuestionOption::class, 'question_id', 'question_id')
            ->orderBy('order_index');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'question_id', 'question_id');
    }

    public function isAutoGradable(): bool
    {
        return in_array($this->question_type, [self::TYPE_MCQ, self::TYPE_TRUE_FALSE], true);
    }
}

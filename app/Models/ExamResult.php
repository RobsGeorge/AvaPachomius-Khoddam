<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_GRADED = 'graded';

    public const STATUS_CHEATER = 'cheater';

    protected $primaryKey = 'result_id';

    protected $fillable = [
        'exam_id',
        'user_id',
        'schedule_id',
        'attempt_id',
        'score',
        'status',
        'auto_score',
        'manual_score',
        'submitted_at',
    ];

    protected $casts = [
        'score'        => 'decimal:2',
        'auto_score'   => 'decimal:2',
        'manual_score' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'schedule_id', 'schedule_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id', 'attempt_id');
    }

    public function isDone(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_GRADED,
            self::STATUS_CHEATER,
        ], true);
    }

    public function isCheater(): bool
    {
        return $this->status === self::STATUS_CHEATER;
    }
}

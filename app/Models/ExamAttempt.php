<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamAttempt extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_GRADED = 'graded';

    public const STATUS_TERMINATED = 'terminated';

    protected $primaryKey = 'attempt_id';

    protected $fillable = [
        'exam_id',
        'schedule_id',
        'user_id',
        'status',
        'answers_json',
        'started_at',
        'checklist_acknowledged_at',
        'submitted_at',
        'proctor_warnings',
        'terminated_for_cheating',
        'terminated_at',
    ];

    protected $casts = [
        'answers_json'              => 'array',
        'started_at'                => 'datetime',
        'checklist_acknowledged_at' => 'datetime',
        'submitted_at'              => 'datetime',
        'proctor_warnings'          => 'integer',
        'terminated_for_cheating'   => 'boolean',
        'terminated_at'             => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'schedule_id', 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'attempt_id', 'attempt_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(ExamResult::class, 'attempt_id', 'attempt_id');
    }

    public function proctorEvents(): HasMany
    {
        return $this->hasMany(ExamProctorEvent::class, 'attempt_id', 'attempt_id');
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_GRADED,
            self::STATUS_TERMINATED,
        ], true);
    }

    public function isTerminatedForCheating(): bool
    {
        return (bool) $this->terminated_for_cheating;
    }

    public function hasStartedAttempt(): bool
    {
        return $this->checklist_acknowledged_at !== null;
    }
}

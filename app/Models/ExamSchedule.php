<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSchedule extends Model
{
    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'exam_id',
        'scheduled_date',
        'is_completed',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'is_completed'   => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class, 'schedule_id', 'schedule_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'schedule_id', 'schedule_id');
    }

    /** Global exam end — same for every student (start + duration). */
    public function endsAt(): \Illuminate\Support\Carbon
    {
        $duration = $this->exam?->duration_minutes ?? 0;

        return $this->scheduled_date->copy()->addMinutes($duration);
    }

    public function hasStarted(): bool
    {
        return now()->gte($this->scheduled_date);
    }

    public function hasEnded(): bool
    {
        return now()->gte($this->endsAt());
    }

    public function isActive(): bool
    {
        return $this->hasStarted() && ! $this->hasEnded();
    }
}

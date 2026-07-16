<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use BelongsToChurch;

    public const TYPE_EXAM = 'exam';

    public const TYPE_QUIZ = 'quiz';

    public const MODE_ONLINE = 'online';

    public const MODE_OFFLINE = 'offline';

    protected $primaryKey = 'exam_id';

    protected $fillable = [
        'course_id',
        'module_id',
        'exam_name',
        'exam_type',
        'delivery_mode',
        'duration_minutes',
        'study_resources',
        'exam_description',
        'passing_score',
        'is_published',
        'total_points',
        'shuffle_questions',
        'allow_late_entry',
    ];

    protected $casts = [
        'passing_score'     => 'integer',
        'is_published'      => 'boolean',
        'total_points'      => 'decimal:2',
        'shuffle_questions' => 'boolean',
        'allow_late_entry'  => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class, 'exam_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class, 'exam_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class, 'exam_id', 'exam_id')
            ->orderBy('order_index');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'exam_id', 'exam_id');
    }

    public function isOnline(): bool
    {
        return $this->delivery_mode === self::MODE_ONLINE;
    }

    public function isOffline(): bool
    {
        return $this->delivery_mode === self::MODE_OFFLINE;
    }

    public function recalculateTotalPoints(): void
    {
        $total = $this->questions()->sum('points');
        $this->update(['total_points' => $total]);
    }
}

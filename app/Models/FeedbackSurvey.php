<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackSurvey extends Model
{
    use BelongsToChurch;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $primaryKey = 'survey_id';

    protected $fillable = [
        'course_id', 'module_id', 'title', 'description', 'created_by_user_id',
        'status', 'is_mandatory', 'due_at', 'opened_at', 'closed_at',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'due_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackQuestion::class, 'survey_id', 'survey_id')
            ->orderBy('order_index');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FeedbackSubmission::class, 'survey_id', 'survey_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN
            && ($this->due_at === null || $this->due_at->isFuture());
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED
            || ($this->due_at !== null && $this->due_at->isPast());
    }

    public function getRouteKeyName(): string
    {
        return 'survey_id';
    }
}

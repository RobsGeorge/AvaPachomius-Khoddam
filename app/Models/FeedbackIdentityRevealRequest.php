<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackIdentityRevealRequest extends Model
{
    use BelongsToChurch;
    use Concerns\SafelyCastsDates;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const DEFAULT_REVEAL_DAYS = 7;

    protected $table = 'feedback_identity_reveal_requests';

    protected $primaryKey = 'reveal_request_id';

    protected $fillable = [
        'church_id',
        'survey_id',
        'submission_id',
        'answer_id',
        'requested_by_user_id',
        'reason',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'expires_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(FeedbackSurvey::class, 'survey_id', 'survey_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeedbackSubmission::class, 'submission_id', 'submission_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(FeedbackAnswer::class, 'answer_id', 'answer_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActiveFor(User $viewer): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        if ((int) $this->requested_by_user_id !== (int) $viewer->user_id) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function getRouteKeyName(): string
    {
        return 'reveal_request_id';
    }
}

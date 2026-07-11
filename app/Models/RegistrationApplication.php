<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationApplication extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_NEEDS_CORRECTION = 'needs_correction';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

  /** @var list<string> */
    public const REVIEWABLE_FIELDS = [
        'first_name',
        'second_name',
        'third_name',
        'national_id',
        'mobile_number',
        'email',
        'job',
        'date_of_birth',
        'profile_photo',
    ];

    protected $fillable = [
        'user_id',
        'status',
        'snapshot',
        'version',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'overall_rejection_note',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }

    public function fieldReviews(): HasMany
    {
        return $this->hasMany(RegistrationApplicationFieldReview::class, 'application_id');
    }

    public function rejectedFields(): HasMany
    {
        return $this->fieldReviews()->where('status', RegistrationApplicationFieldReview::STATUS_REJECTED);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_NEEDS_CORRECTION,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }
}

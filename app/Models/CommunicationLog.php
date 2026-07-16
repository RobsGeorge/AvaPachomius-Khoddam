<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    use BelongsToChurch;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_PORTAL = 'portal';

    public const CHANNEL_ANNOUNCEMENT = 'announcement';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'user_id',
        'recipient_name',
        'recipient_email',
        'recipient_mobile',
        'channel',
        'status',
        'subject',
        'body_preview',
        'course_id',
        'service_id',
        'sent_by_user_id',
        'related_type',
        'related_id',
        'tracking_token',
        'sent_at',
        'failed_at',
        'opened_at',
        'read_at',
        'failure_reason',
        'provider_message_id',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'opened_at' => 'datetime',
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return list<string> */
    public static function channels(): array
    {
        return [
            self::CHANNEL_EMAIL,
            self::CHANNEL_SMS,
            self::CHANNEL_WHATSAPP,
            self::CHANNEL_PORTAL,
            self::CHANNEL_ANNOUNCEMENT,
        ];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_SENT,
            self::STATUS_FAILED,
            self::STATUS_SKIPPED,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id', 'user_id');
    }

    public function wasOpened(): bool
    {
        return $this->opened_at !== null || $this->read_at !== null;
    }

    public function wasSuccessful(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function destination(): ?string
    {
        return match ($this->channel) {
            self::CHANNEL_EMAIL => $this->recipient_email,
            self::CHANNEL_SMS, self::CHANNEL_WHATSAPP => $this->recipient_mobile,
            default => $this->recipient_email ?: $this->recipient_mobile,
        };
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCourse(Builder $query, int $courseId): Builder
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }
}

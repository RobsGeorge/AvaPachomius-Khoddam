<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Announcement extends Model
{
    use BelongsToChurch;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const TARGET_COURSE = 'course';

    public const TARGET_SERVICE = 'service';

    public const TARGET_USERS = 'users';

    public const CHANNEL_HOMEPAGE = 'homepage';

    public const CHANNEL_BANNER_DISMISSIBLE = 'banner_dismissible';

    public const CHANNEL_BANNER_LOCKED = 'banner_locked';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $primaryKey = 'announcement_id';

    protected $fillable = [
        'created_by_user_id',
        'course_id',
        'service_id',
        'title',
        'body',
        'target_mode',
        'channels',
        'status',
        'banner_starts_at',
        'banner_ends_at',
        'published_at',
        'published_by_user_id',
    ];

    protected $casts = [
        'channels' => 'array',
        'banner_starts_at' => 'datetime',
        'banner_ends_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id', 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function targetUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_target_users', 'announcement_id', 'user_id', 'announcement_id', 'user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AnnouncementDelivery::class, 'announcement_id', 'announcement_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AnnouncementRevision::class, 'announcement_id', 'announcement_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Whether a published announcement is within its optional visibility window.
     * Null start/end means open-ended on that side.
     */
    public function isCurrentlyVisible(?Carbon $at = null): bool
    {
        if (! $this->isPublished()) {
            return false;
        }

        $at = $at ?? now(config('attendance.timezone', config('app.timezone')));

        if ($this->banner_starts_at && $this->banner_starts_at->gt($at)) {
            return false;
        }

        if ($this->isFinished($at)) {
            return false;
        }

        return true;
    }

    /**
     * Published announcements whose end date has passed (list "Finished" group).
     */
    public function isFinished(?Carbon $at = null): bool
    {
        if (! $this->isPublished() || ! $this->banner_ends_at) {
            return false;
        }

        $at = $at ?? now(config('attendance.timezone', config('app.timezone')));

        return $this->banner_ends_at->lt($at);
    }

    /** @param Builder<static> $query */
    public function scopeCurrentlyVisible(Builder $query, ?Carbon $at = null): Builder
    {
        $at = $at ?? now(config('attendance.timezone', config('app.timezone')));

        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $inner) use ($at) {
                $inner->whereNull('banner_starts_at')->orWhere('banner_starts_at', '<=', $at);
            })
            ->where(function (Builder $inner) use ($at) {
                $inner->whereNull('banner_ends_at')->orWhere('banner_ends_at', '>=', $at);
            });
    }

    /** @param Builder<static> $query */
    public function scopeFinished(Builder $query, ?Carbon $at = null): Builder
    {
        $at = $at ?? now(config('attendance.timezone', config('app.timezone')));

        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('banner_ends_at')
            ->where('banner_ends_at', '<', $at);
    }

    public function hasChannel(string $channel): bool
    {
        return (bool) ($this->channels[$channel] ?? false);
    }

    /** @return list<string> */
    public static function channelOptions(): array
    {
        return [
            self::CHANNEL_HOMEPAGE,
            self::CHANNEL_BANNER_DISMISSIBLE,
            self::CHANNEL_BANNER_LOCKED,
            self::CHANNEL_EMAIL,
            self::CHANNEL_WHATSAPP,
        ];
    }
}

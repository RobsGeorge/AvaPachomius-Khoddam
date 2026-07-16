<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Event extends Model
{
    use BelongsToChurch;

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at', 'capacity',
        'registration_opens_at', 'registration_closes_at', 'course_id', 'visibility',
        'eligible_roles', 'status', 'check_in_token', 'created_by_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'eligible_roles' => 'array',
        'capacity' => 'integer',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    public static function generateCheckInToken(): string
    {
        return Str::random(48);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id', 'user_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(EventReservation::class, 'event_id', 'event_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(EventReservationException::class, 'event_id', 'event_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(EventCheckIn::class, 'event_id', 'event_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function confirmedCount(): int
    {
        return $this->reservations()->where('status', EventReservation::STATUS_CONFIRMED)->count();
    }

    public function waitlistCount(): int
    {
        return $this->reservations()->where('status', EventReservation::STATUS_WAITLIST)->count();
    }

    public function seatsRemaining(): int
    {
        return max(0, $this->capacity - $this->confirmedCount());
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('ends_at', '>=', now());
    }

    public function signedCheckInUrlFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'events.check-in.verify',
            $this->ends_at,
            ['event' => $this->event_id, 'user' => $user->user_id]
        );
    }
}

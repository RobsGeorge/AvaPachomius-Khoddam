<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReservation extends Model
{
    protected $primaryKey = 'reservation_id';

    protected $fillable = [
        'event_id', 'user_id', 'status', 'reserved_at', 'cancelled_at',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_WAITLIST = 'waitlist';
    public const STATUS_CANCELLED = 'cancelled';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_WAITLIST], true);
    }
}

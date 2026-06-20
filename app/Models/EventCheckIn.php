<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCheckIn extends Model
{
    protected $primaryKey = 'check_in_id';

    protected $fillable = [
        'event_id', 'user_id', 'reservation_id', 'checked_in_at', 'checked_in_by_id',
    ];

    protected $casts = ['checked_in_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(EventReservation::class, 'reservation_id', 'reservation_id');
    }
}

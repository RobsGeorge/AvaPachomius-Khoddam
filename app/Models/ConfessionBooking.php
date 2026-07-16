<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfessionBooking extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'confession_booking';

    protected $primaryKey = 'confession_booking_id';

    public function getRouteKeyName(): string
    {
        return 'confession_booking_id';
    }

    protected $fillable = [
        'confession_slot_id',
        'user_id',
        'status',
        'notes',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ConfessionSlot::class, 'confession_slot_id', 'confession_slot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

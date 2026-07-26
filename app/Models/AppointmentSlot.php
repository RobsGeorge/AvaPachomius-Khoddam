<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentSlot extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'appointment_slot';

    protected $primaryKey = 'appointment_slot_id';

    public function getRouteKeyName(): string
    {
        return 'appointment_slot_id';
    }

    protected $fillable = [
        'priest_id',
        'appointment_type_id',
        'starts_at',
        'ends_at',
        'capacity',
        'location',
        'recurrence',
        'status',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'capacity' => 'integer',
    ];

    public function priest(): BelongsTo
    {
        return $this->belongsTo(Priest::class, 'priest_id', 'priest_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id', 'appointment_type_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AppointmentBooking::class, 'appointment_slot_id', 'appointment_slot_id');
    }

    public function confirmedBookings(): HasMany
    {
        return $this->bookings()->where('status', AppointmentBooking::STATUS_CONFIRMED);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function remainingCapacity(): int
    {
        $taken = $this->relationLoaded('confirmedBookings')
            ? $this->confirmedBookings->count()
            : $this->confirmedBookings()->count();

        return max(0, (int) $this->capacity - $taken);
    }
}

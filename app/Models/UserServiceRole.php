<?php

namespace App\Models;

use App\Support\Structure\RosterStatus;
use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class UserServiceRole extends Model
{
    use BelongsToChurch;

    public const ROSTER_ACTIVE = RosterStatus::ACTIVE;

    public const ROSTER_INACTIVE = RosterStatus::INACTIVE;

    public const ROSTER_LEFT = RosterStatus::LEFT;

    public const ROSTER_PASTORAL_HOLD = RosterStatus::PASTORAL_HOLD;

    protected $table = 'user_service_role';

    protected $primaryKey = 'user_service_role_id';

    protected $fillable = [
        'user_id',
        'service_id',
        'role_id',
        'is_primary',
        'roster_status',
        'status_note',
        'status_changed_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'status_changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserServiceRole $row) {
            if (! Schema::hasColumn($row->getTable(), 'roster_status')) {
                return;
            }
            if (blank($row->roster_status)) {
                $row->roster_status = RosterStatus::ACTIVE;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeForService(Builder $query, int|string $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }
}

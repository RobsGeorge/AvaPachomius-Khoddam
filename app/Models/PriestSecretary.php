<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriestSecretary extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'priest_secretary';

    protected $primaryKey = 'priest_secretary_id';

    public function getRouteKeyName(): string
    {
        return 'priest_secretary_id';
    }

    protected $fillable = [
        'priest_id',
        'user_id',
        'status',
    ];

    public function priest(): BelongsTo
    {
        return $this->belongsTo(Priest::class, 'priest_id', 'priest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}

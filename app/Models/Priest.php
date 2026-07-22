<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Priest extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'priest';

    protected $primaryKey = 'priest_id';

    public function getRouteKeyName(): string
    {
        return 'priest_id';
    }

    protected $fillable = [
        'user_id',
        'title',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ConfessionSlot::class, 'priest_id', 'priest_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function displayName(): string
    {
        $user = $this->user;
        if (! $user) {
            return (string) ($this->title ?: '#'.$this->priest_id);
        }

        $name = trim(($user->first_name ?? '').' '.($user->second_name ?? ''));

        return $this->title ? ($this->title.' — '.$name) : ($name !== '' ? $name : (string) $user->email);
    }
}

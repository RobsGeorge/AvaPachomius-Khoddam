<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A church — the tenant / isolation boundary of the platform
 * (see docs/khedma-master-plan.md). T0: model + membership only; the
 * BelongsToChurch global scope arrives in T1.
 */
class Church extends Model
{
    protected $table = 'church';

    protected $primaryKey = 'church_id';

    protected $fillable = ['slug', 'name', 'domain', 'status', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function members(): HasMany
    {
        return $this->hasMany(ChurchUser::class, 'church_id', 'church_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'church_user', 'church_id', 'user_id', 'church_id', 'user_id')
            ->withPivot('status', 'joined_at');
    }

    /** The default church that all pre-existing data was backfilled into (Tenant Zero). */
    public static function main(): self
    {
        return static::where('slug', config('tenancy.main_slug'))->firstOrFail();
    }
}

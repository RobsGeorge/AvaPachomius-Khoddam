<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical tenant registry (organizations shape, master-plan §4).
 * Product-facing code still uses {@see Church} + church_id during expand-contract.
 */
class Organization extends Model
{
    protected $table = 'organizations';

    protected $primaryKey = 'organization_id';

    protected $fillable = [
        'parent_id',
        'type',
        'subdomain',
        'name',
        'region',
        'theme',
        'settings',
        'onboarding_state',
        'status',
    ];

    protected $casts = [
        'theme' => 'array',
        'settings' => 'array',
        'onboarding_state' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'organization_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'organization_id');
    }

    public static function main(): self
    {
        return static::where('subdomain', config('tenancy.main_slug', 'avapakhomios'))->firstOrFail();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical tenant registry (organizations shape, master-plan §4).
 * Product-facing code still uses {@see Church} + church_id during expand-contract.
 *
 * Placement columns (placement_policy, db_isolated, db_*) are the diocese-tier
 * residency seam — defaults keep every org on the shared DB.
 */
class Organization extends Model
{
    public const TYPE_CHURCH = 'church';

    public const TYPE_DIOCESE = 'diocese';

    public const TYPE_PATRIARCHATE = 'patriarchate';

    public const PLACEMENT_SHARED = 'shared';

    public const PLACEMENT_DIOCESE_DB = 'diocese_db';

    public const PLACEMENT_CHURCH_DB = 'church_db';

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
        'placement_policy',
        'db_isolated',
        'db_name',
        'db_user',
        'db_password_encrypted',
    ];

    protected $hidden = [
        'db_password_encrypted',
        'documents_dek_wrapped',
    ];

    protected $casts = [
        'theme' => 'array',
        'settings' => 'array',
        'onboarding_state' => 'array',
        'db_isolated' => 'boolean',
        'db_password_encrypted' => 'encrypted',
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

<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Temporal household / address fact (≠ family graph).
 * Canary for {@see BelongsToTenant} after Family soft-deprecation.
 */
class Residence extends Model
{
    use BelongsToChurch;
    use BelongsToTenant;

    protected $table = 'residences';

    protected $primaryKey = 'residence_id';

    protected $fillable = [
        'church_id',
        'address',
        'geo',
        'notes',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ResidenceMember::class, 'residence_id', 'residence_id');
    }

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }
}

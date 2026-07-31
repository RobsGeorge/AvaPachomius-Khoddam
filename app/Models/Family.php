<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Household container (canary for {@see BelongsToTenant} — low UI blast radius).
 * FamilyMember stays on the default connection until a later residency slice.
 */
class Family extends Model
{
    use BelongsToChurch;
    use BelongsToTenant;

    protected $table = 'families';

    protected $primaryKey = 'family_id';

    protected $fillable = ['church_id', 'name'];

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class, 'family_id', 'family_id');
    }
}

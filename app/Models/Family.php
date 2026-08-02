<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @deprecated Soft-deprecated 2026-08-24 (ADR §21: family is edges, never a container).
 * Tables retained until Phase-5 contraction. BelongsToTenant canary moved to {@see Residence}.
 * Do not use for new product paths — use Relationship edges + Residence instead.
 */
class Family extends Model
{
    use BelongsToChurch;

    protected $table = 'families';

    protected $primaryKey = 'family_id';

    protected $fillable = ['church_id', 'name'];

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class, 'family_id', 'family_id');
    }
}

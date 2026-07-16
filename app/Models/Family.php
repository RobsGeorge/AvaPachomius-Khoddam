<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    protected $table = 'family_members';

    protected $primaryKey = 'family_member_id';

    protected $fillable = ['family_id', 'person_id', 'role'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'family_id', 'family_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }
}

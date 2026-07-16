<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    use BelongsToChurch;

    protected $table = 'relationships';

    protected $primaryKey = 'relationship_id';

    protected $fillable = [
        'church_id',
        'person_id',
        'related_person_id',
        'type',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id', 'person_id');
    }
}

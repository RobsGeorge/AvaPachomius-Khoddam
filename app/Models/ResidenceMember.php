<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidenceMember extends Model
{
    protected $table = 'residence_members';

    protected $primaryKey = 'residence_member_id';

    protected $fillable = [
        'residence_id',
        'person_id',
        'start_date',
        'end_date',
        'role_in_home',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class, 'residence_id', 'residence_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }

    public function isActive(): bool
    {
        return $this->end_date === null;
    }
}

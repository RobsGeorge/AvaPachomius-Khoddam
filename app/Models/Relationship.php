<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    use BelongsToChurch;

    public const TYPE_GUARDIAN_OF = 'guardian_of';

    public const TYPE_CHILD_OF = 'child_of';

    public const TYPE_SPOUSE_OF = 'spouse_of';

    public const TYPE_SIBLING_OF = 'sibling_of';

    public const VISIBILITY_FULL = 'full';

    public const VISIBILITY_RESTRICTED = 'restricted';

    protected $table = 'relationships';

    protected $primaryKey = 'relationship_id';

    protected $fillable = [
        'church_id',
        'person_id',
        'related_person_id',
        'type',
        'start_date',
        'end_date',
        'verified_by',
        'guardian_visibility',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Relationship $relationship) {
            if ($relationship->guardian_visibility === null || $relationship->guardian_visibility === '') {
                $relationship->guardian_visibility = self::VISIBILITY_FULL;
            }
            if ($relationship->start_date === null) {
                $relationship->start_date = now()->toDateString();
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id', 'person_id');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }

    public function scopeGuardianOf(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_GUARDIAN_OF);
    }

    public function isActive(): bool
    {
        return $this->end_date === null;
    }

    public function isRestrictedVisibility(): bool
    {
        return $this->guardian_visibility === self::VISIBILITY_RESTRICTED;
    }
}

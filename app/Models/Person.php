<?php

namespace App\Models;

use App\Support\ArabicNameNormalizer;
use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use BelongsToChurch;

    protected $table = 'people';

    protected $primaryKey = 'person_id';

    protected $fillable = [
        'church_id',
        'first_name',
        'second_name',
        'third_name',
        'display_name',
        'normalized_name',
        'date_of_birth',
        'mobile_number',
        'national_id',
        'email',
        'gender',
        'retired_at',
        'merged_into_person_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'retired_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Person $person) {
            if ($person->display_name === null || $person->display_name === '') {
                $person->display_name = User::fullNameFromParts(
                    (string) $person->first_name,
                    (string) $person->second_name,
                    (string) $person->third_name
                ) ?: $person->email;
            }

            $person->normalized_name = ArabicNameNormalizer::normalize(
                $person->display_name ?: ArabicNameNormalizer::fromParts(
                    $person->first_name,
                    $person->second_name,
                    $person->third_name
                )
            );
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_person_id', 'person_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'person_id', 'person_id');
    }

    public function familyMemberships(): HasMany
    {
        return $this->hasMany(FamilyMember::class, 'person_id', 'person_id');
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'person_id', 'person_id');
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }
}

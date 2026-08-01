<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgePolicy extends Model
{
    protected $table = 'age_policies';

    protected $primaryKey = 'age_policy_id';

    protected $fillable = [
        'organization_id',
        'digital_consent_age',
        'age_of_majority',
    ];

    protected $casts = [
        'digital_consent_age' => 'integer',
        'age_of_majority' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }
}

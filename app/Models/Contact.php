<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Non-unique contact attribute layer (ADR §12 / §24).
 * Shared landline/mobile across people and residences is allowed — no unique
 * constraint on value (or type+value). Distinct from login identifiers.
 */
class Contact extends Model
{
    use BelongsToChurch;

    public const TYPE_MOBILE = 'mobile';

    public const TYPE_EMAIL = 'email';

    public const TYPE_LANDLINE = 'landline';

    public const CONTACTABLE_PERSON = 'person';

    public const CONTACTABLE_RESIDENCE = 'residence';

    protected $table = 'contacts';

    protected $primaryKey = 'contact_id';

    protected $fillable = [
        'church_id',
        'contactable_type',
        'contactable_id',
        'type',
        'value',
        'is_primary',
        'verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}

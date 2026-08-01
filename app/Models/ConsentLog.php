<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only DPIA consent artifact. Never UPDATE or DELETE rows.
 */
class ConsentLog extends Model
{
    use BelongsToChurch;

    public const UPDATED_AT = null;

    public const SCOPE_GUARDIAN_CUSTODY = 'guardian_custody';

    public const SCOPE_RUNG2_CREDENTIAL = 'rung2_credential';

    public const SCOPE_SELF_EMANCIPATION = 'self_emancipation';

    protected $table = 'consent_log';

    protected $primaryKey = 'consent_log_id';

    protected $fillable = [
        'church_id',
        'person_id',
        'consented_by',
        'scope',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('consent_log is append-only; never UPDATE.');
        });

        static::deleting(function () {
            throw new LogicException('consent_log is append-only; never DELETE.');
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function consentedByPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'consented_by', 'person_id');
    }
}

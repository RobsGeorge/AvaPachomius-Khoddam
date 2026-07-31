<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only access ledger row (trust artifact). Never update or delete.
 */
class AccessLedgerEntry extends Model
{
    public const ACTOR_USER = 'user';

    public const ACTOR_STAFF = 'staff';

    public const ACTOR_SYSTEM = 'system';

    public const ACTION_READ = 'read';

    public const ACTION_WRITE = 'write';

    public const ACTION_EXPORT = 'export';

    public const ACTION_BREAK_GLASS_OPEN = 'break_glass_open';

    public const ACTION_RECOVERY = 'recovery';

    public const ACTION_MERGE = 'merge';

    public $timestamps = false;

    protected $table = 'access_ledger';

    protected $primaryKey = 'access_ledger_id';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'church_id',
        'organization_id',
        'context',
        'prev_hash',
        'row_hash',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
        'actor_id' => 'integer',
        'subject_id' => 'integer',
        'church_id' => 'integer',
        'organization_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('access_ledger is append-only; updates are forbidden.');
        });

        static::deleting(function () {
            throw new LogicException('access_ledger is append-only; deletes are forbidden.');
        });
    }
}

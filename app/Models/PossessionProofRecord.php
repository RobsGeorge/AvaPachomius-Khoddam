<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DB row for a minted possession proof. The in-memory VO is App\Services\Auth\Recovery\PossessionProof.
 */
class PossessionProofRecord extends Model
{
    public $timestamps = false;

    protected $table = 'possession_proofs';

    protected $primaryKey = 'possession_proof_id';

    protected $fillable = [
        'account_recovery_challenge_id',
        'user_id',
        'token_hash',
        'purpose',
        'expires_at',
        'consumed_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'created_at' => 'datetime',
        'account_recovery_challenge_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(AccountRecoveryChallenge::class, 'account_recovery_challenge_id', 'account_recovery_challenge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

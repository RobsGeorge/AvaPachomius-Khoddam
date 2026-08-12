<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountRecoveryChallenge extends Model
{
    public const TIER_SELF_SERVE = 'self_serve';

    public const TIER_ADMIN_ASSISTED = 'admin_assisted';

    public const TIER_SUPPORT = 'support';

    public const PURPOSE_REBIND_MOBILE = 'rebind_mobile';

    public const PURPOSE_REBIND_EMAIL = 'rebind_email';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const PHASE_PROOF = 'proof';

    public const PHASE_ASSERTED = 'asserted';

    public const PHASE_COMPLETED = 'completed';

    public const OUTCOME_PENDING = 'pending';

    public const OUTCOME_OTP_SENT = 'otp_sent';

    public const OUTCOME_VERIFIED = 'verified';

    public const OUTCOME_COMPLETED = 'completed';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_RATE_LIMITED = 'rate_limited';

    public $timestamps = false;

    protected $table = 'account_recovery_challenges';

    protected $primaryKey = 'account_recovery_challenge_id';

    protected $fillable = [
        'user_id',
        'tier',
        'purpose',
        'phase',
        'proof_channel',
        'asserted_channel',
        'asserted_value',
        'vouched_by_user_id',
        'otp_hash',
        'otp_expires_at',
        'outcome',
        'consumed_at',
        'created_at',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'created_at' => 'datetime',
        'user_id' => 'integer',
        'vouched_by_user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function vouchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vouched_by_user_id', 'user_id');
    }

    public function possessionProof(): HasOne
    {
        return $this->hasOne(PossessionProofRecord::class, 'account_recovery_challenge_id', 'account_recovery_challenge_id');
    }
}

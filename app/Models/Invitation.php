<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use BelongsToChurch;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_FAILED = 'failed';

    protected $table = 'invitations';

    protected $primaryKey = 'invitation_id';

    protected $fillable = [
        'church_id',
        'person_id',
        'user_id',
        'email',
        'mobile_number',
        'send_email',
        'send_whatsapp',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'invited_by_user_id',
        'service_id',
        'course_id',
        'intended_role_id',
        'person_placement_id',
        'prefilled_profile',
        'last_email_error',
        'last_whatsapp_error',
    ];

    protected $casts = [
        'send_email' => 'boolean',
        'send_whatsapp' => 'boolean',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'prefilled_profile' => 'array',
    ];

    /** @return array{invitation: self, plain_token: string} */
    public static function issueToken(): array
    {
        $plain = Str::random(64);

        return [
            'plain_token' => $plain,
            'token_hash' => hash('sha256', $plain),
        ];
    }

    public static function findPendingByPlainToken(string $plainToken): ?self
    {
        $hash = hash('sha256', $plainToken);

        return static::query()
            ->where('token_hash', $hash)
            ->where('status', self::STATUS_PENDING)
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAcceptable(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isExpired();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id', 'user_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(PersonPlacement::class, 'person_placement_id', 'person_placement_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }
}

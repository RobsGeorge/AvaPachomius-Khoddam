<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChurchRegistrationQrToken extends Model
{
    use BelongsToChurch;

    protected $table = 'church_registration_qr_tokens';

    protected $primaryKey = 'church_registration_qr_token_id';

    protected $fillable = [
        'church_id',
        'token_hash',
        'expires_at',
        'rotated_at',
        'created_by_user_id',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return array{plain_token: string, token_hash: string} */
    public static function issueToken(): array
    {
        $plain = Str::random(48);

        return [
            'plain_token' => $plain,
            'token_hash' => hash('sha256', $plain),
        ];
    }

    public static function findUsableByPlainToken(string $plainToken): ?self
    {
        $hash = hash('sha256', $plainToken);

        /** @var self|null $token */
        $token = static::query()
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if (! $token || $token->isExpired()) {
            return null;
        }

        return $token;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && ! $this->isExpired();
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }
}

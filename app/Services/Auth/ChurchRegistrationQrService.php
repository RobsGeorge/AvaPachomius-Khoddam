<?php

namespace App\Services\Auth;

use App\Models\Church;
use App\Models\ChurchRegistrationQrToken;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChurchRegistrationQrService
{
    public const SESSION_TOKEN_ID = 'registration_qr_token_id';

    public const SESSION_LANE = 'registration_lane';

    public const DEFAULT_TTL_DAYS = 7;

    /**
     * Mint a rotating church QR token. Revokes prior active tokens for the church.
     *
     * @return array{token: ChurchRegistrationQrToken, plain_token: string, payload: array{organization_id: int, rotating_token: string}}
     */
    public function mint(Church $church, ?User $actor = null, int $ttlDays = self::DEFAULT_TTL_DAYS): array
    {
        if (! Schema::hasTable('church_registration_qr_tokens')) {
            throw new \RuntimeException('church_registration_qr_tokens schema is not migrated.');
        }

        return DB::transaction(function () use ($church, $actor, $ttlDays) {
            $now = now();

            ChurchRegistrationQrToken::query()
                ->where('church_id', $church->church_id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'rotated_at' => $now,
                ]);

            $issued = ChurchRegistrationQrToken::issueToken();
            $token = ChurchRegistrationQrToken::withoutTenancy()->create([
                'church_id' => $church->church_id,
                'token_hash' => $issued['token_hash'],
                'expires_at' => $now->copy()->addDays(max(1, $ttlDays)),
                'created_by_user_id' => $actor?->user_id,
            ]);

            AuditLogService::recordEvent('registration.qr_token_minted', [
                'church_id' => $church->church_id,
                'church_registration_qr_token_id' => $token->church_registration_qr_token_id,
                'expires_at' => $token->expires_at?->toIso8601String(),
            ]);

            return [
                'token' => $token,
                'plain_token' => $issued['plain_token'],
                'payload' => [
                    'organization_id' => (int) $church->church_id,
                    'rotating_token' => $issued['plain_token'],
                ],
            ];
        });
    }

    public function findUsable(string $plainToken): ?ChurchRegistrationQrToken
    {
        if (! Schema::hasTable('church_registration_qr_tokens') || $plainToken === '') {
            return null;
        }

        return ChurchRegistrationQrToken::findUsableByPlainToken($plainToken);
    }

    /** Bind a validated QR token into the session for the register form. */
    public function bindToSession(ChurchRegistrationQrToken $token): void
    {
        session([
            self::SESSION_TOKEN_ID => $token->church_registration_qr_token_id,
            self::SESSION_LANE => User::REGISTRATION_LANE_QR,
        ]);
    }

    public function clearSession(): void
    {
        session()->forget([self::SESSION_TOKEN_ID, self::SESSION_LANE]);
    }

    public function sessionToken(): ?ChurchRegistrationQrToken
    {
        $id = session(self::SESSION_TOKEN_ID);
        if (! $id || ! Schema::hasTable('church_registration_qr_tokens')) {
            return null;
        }

        $token = ChurchRegistrationQrToken::withoutTenancy()->find($id);

        return $token && $token->isUsable() ? $token : null;
    }
}

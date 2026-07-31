<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class LoginResolutionService
{
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_MOBILE = 'mobile';

    /**
     * @return self::CHANNEL_*|null
     */
    public function classify(string $identifier): ?string
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return self::CHANNEL_EMAIL;
        }

        $digits = preg_replace('/\D+/', '', $identifier) ?? '';

        if ($digits !== '' && preg_match('/^(?:20)?0?1[0-9]{9}$/', $digits)) {
            return self::CHANNEL_MOBILE;
        }

        return null;
    }

    /**
     * @return array{user: User, channel: string}|null
     */
    public function resolve(string $identifier): ?array
    {
        $channel = $this->classify($identifier);

        if ($channel === self::CHANNEL_EMAIL) {
            $user = User::query()->where('email', trim($identifier))->first();

            return $user ? ['user' => $user, 'channel' => self::CHANNEL_EMAIL] : null;
        }

        if ($channel === self::CHANNEL_MOBILE) {
            if (! Schema::hasColumn('user', 'mobile_verified_at')) {
                return null;
            }

            $user = User::query()
                ->whereIn('mobile_number', $this->mobileLookupValues($identifier))
                ->whereNotNull('mobile_verified_at')
                ->first();

            return $user ? ['user' => $user, 'channel' => self::CHANNEL_MOBILE] : null;
        }

        return null;
    }

    /**
     * Egyptian mobile variants: local 01…, stored 10-digit, and 20… country prefix.
     *
     * @return list<string>
     */
    public function mobileLookupValues(string $identifier): array
    {
        $digits = preg_replace('/\D+/', '', $identifier) ?? '';

        if ($digits === '') {
            return [];
        }

        if (str_starts_with($digits, '20') && strlen($digits) >= 12) {
            $local = ltrim(substr($digits, 2), '0');
        } elseif (str_starts_with($digits, '0')) {
            $local = ltrim(substr($digits, 1), '0');
        } else {
            $local = ltrim($digits, '0');
        }

        if ($local === '') {
            return [];
        }

        return array_values(array_unique([
            $local,
            '0'.$local,
            '20'.$local,
        ]));
    }
}

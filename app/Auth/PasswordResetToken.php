<?php

namespace App\Auth;

/**
 * Password-reset tokens as they appear in emailed URLs.
 *
 * Laravel's HMAC tokens are hex, but RTL email clients (Outlook, some Gmail
 * views) inject Unicode bidi marks, wrap/insert whitespace, or case-fold the
 * path. Any of those makes Hash::check fail with passwords.token.
 */
final class PasswordResetToken
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function normalize(?string $token): string
    {
        $token = rawurldecode((string) $token);
        $token = preg_replace(
            '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{2066}-\x{2069}\x{FEFF}]/u',
            '',
            $token
        ) ?? $token;
        $token = preg_replace('/\s+/u', '', $token) ?? $token;
        $token = trim($token);

        if ($token !== '' && preg_match('/^[0-9a-fA-F]+$/', $token) === 1) {
            return strtolower($token);
        }

        return $token;
    }
}

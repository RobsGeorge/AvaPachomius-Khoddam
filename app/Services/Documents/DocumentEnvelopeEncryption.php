<?php

namespace App\Services\Documents;

use App\Models\Church;
use App\Models\Organization;
use App\Tenancy\TenantDatabaseResolver;
use RuntimeException;
use Throwable;

/**
 * Envelope encryption for sensitive documents.
 *
 * Per-placement-organization DEK wrapped by a master key. Only ciphertext and
 * encryption_key_ref are persisted — never plaintext keys in audit/ledger/API.
 *
 * Prefers libsodium secretbox when ext-sodium is loaded; otherwise AES-256-GCM
 * via OpenSSL (local PHP 8.1 boxes without sodium).
 */
final class DocumentEnvelopeEncryption
{
    private const SODIUM_PREFIX = 's1:';

    private const OPENSSL_PREFIX = 'o1:';

    public function keyRefFor(Organization $organization): string
    {
        return 'org:'.(int) $organization->organization_id.':dek:v1';
    }

    public function resolvePlacementOrganization(Church $church): Organization
    {
        $placement = TenantDatabaseResolver::resolvePlacementOrganization($church);
        if ($placement) {
            return $placement;
        }

        if ($church->organization_id) {
            $org = Organization::query()->find($church->organization_id);
            if ($org) {
                return $org;
            }
        }

        return Organization::main();
    }

    /**
     * @return array{ciphertext: string, encryption_key_ref: string}
     */
    public function encrypt(string $plaintext, Church $church): array
    {
        $org = $this->resolvePlacementOrganization($church);
        $dek = $this->ensureDataKey($org);

        return [
            'ciphertext' => $this->seal($plaintext, $dek),
            'encryption_key_ref' => $this->keyRefFor($org),
        ];
    }

    /**
     * @throws RuntimeException when the key is missing/wrong or ciphertext is corrupt
     */
    public function decrypt(string $ciphertext, string $encryptionKeyRef, ?Organization $organization = null): string
    {
        $org = $organization ?? $this->organizationFromKeyRef($encryptionKeyRef);
        if ($org === null) {
            throw new RuntimeException('Document decryption failed: unknown encryption_key_ref.');
        }

        if ($this->keyRefFor($org) !== $encryptionKeyRef) {
            throw new RuntimeException('Document decryption failed: encryption_key_ref mismatch.');
        }

        $dek = $this->unwrapDataKey($org);
        if ($dek === null) {
            throw new RuntimeException('Document decryption failed: organization data key missing.');
        }

        $plain = $this->open($ciphertext, $dek);
        if ($plain === null) {
            throw new RuntimeException('Document decryption failed: invalid key or ciphertext.');
        }

        return $plain;
    }

    public function ensureDataKey(Organization $organization): string
    {
        $existing = $this->unwrapDataKey($organization);
        if ($existing !== null) {
            return $existing;
        }

        $dek = random_bytes(32);
        $organization->forceFill([
            'documents_dek_wrapped' => $this->wrapDataKey($dek),
        ])->save();

        return $dek;
    }

    public function unwrapDataKey(Organization $organization): ?string
    {
        $wrapped = $organization->documents_dek_wrapped;
        if (! is_string($wrapped) || $wrapped === '') {
            return null;
        }

        $binary = base64_decode($wrapped, true);
        if ($binary === false) {
            return null;
        }

        return $this->open($binary, $this->masterKeyBytes());
    }

    public function organizationFromKeyRef(string $ref): ?Organization
    {
        if (! preg_match('/^org:(\d+):dek:v1$/', $ref, $m)) {
            return null;
        }

        return Organization::query()->find((int) $m[1]);
    }

    private function wrapDataKey(string $dek): string
    {
        return base64_encode($this->seal($dek, $this->masterKeyBytes()));
    }

    private function seal(string $plaintext, string $key): string
    {
        if ($this->hasSodium()) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

            return self::SODIUM_PREFIX.$nonce.$cipher;
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($cipher === false) {
            throw new RuntimeException('Document encryption failed (openssl).');
        }

        return self::OPENSSL_PREFIX.$iv.$tag.$cipher;
    }

    private function open(string $payload, string $key): ?string
    {
        if (str_starts_with($payload, self::SODIUM_PREFIX)) {
            if (! $this->hasSodium()) {
                return null;
            }

            $raw = substr($payload, strlen(self::SODIUM_PREFIX));
            if (strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return null;
            }

            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            try {
                $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            } catch (Throwable) {
                return null;
            }

            return $plain === false ? null : $plain;
        }

        if (str_starts_with($payload, self::OPENSSL_PREFIX)) {
            $raw = substr($payload, strlen(self::OPENSSL_PREFIX));
            // iv(12) + tag(16) + cipher
            if (strlen($raw) < 28) {
                return null;
            }

            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);

            $plain = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return $plain === false ? null : $plain;
        }

        return null;
    }

    private function hasSodium(): bool
    {
        return extension_loaded('sodium')
            && defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')
            && defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES');
    }

    private function masterKeyBytes(): string
    {
        $configured = config('documents.master_key');
        if (is_string($configured) && $configured !== '') {
            return hash('sha256', $configured, true);
        }

        $appKey = (string) config('app.key');
        if ($appKey === '') {
            throw new RuntimeException('Documents master key is not configured.');
        }

        return hash('sha256', 'documents-envelope|'.$appKey, true);
    }
}

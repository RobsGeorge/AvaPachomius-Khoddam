<?php

namespace App\Support\AccessLedger;

/**
 * Tamper-evident hash chain for {@see \App\Models\AccessLedgerEntry} rows.
 * row_hash = sha256(prev_hash + canonical_payload).
 */
final class LedgerHashChain
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * @param  array<string, mixed>  $payload  Fields excluding access_ledger_id and row_hash
     */
    public static function hashRow(string $prevHash, array $payload): string
    {
        return hash('sha256', $prevHash.self::canonicalPayload($payload));
    }

    /**
     * Stable JSON: sorted keys, unescaped unicode/slashes, no pretty-print.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function canonicalPayload(array $payload): string
    {
        unset($payload['access_ledger_id'], $payload['row_hash']);
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::sortRecursive($value);
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode access ledger payload.');
        }

        return $json;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function sortRecursive(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn ($item) => is_array($item) ? self::sortRecursive($item) : $item,
                $value
            );
        }

        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = self::sortRecursive($v);
            }
        }

        return $value;
    }
}

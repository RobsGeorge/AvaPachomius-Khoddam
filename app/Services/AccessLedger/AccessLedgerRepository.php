<?php

namespace App\Services\AccessLedger;

use App\Models\AccessLedgerEntry;
use App\Support\AccessLedger\LedgerHashChain;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Insert-only access ledger. Corrections are new rows; never UPDATE/DELETE.
 *
 * context must stay inspectable as a church trust artifact: identifiers and
 * action metadata only — never full row dumps or sensitive pastoral content.
 */
final class AccessLedgerRepository
{
    /** @var list<string> */
    private const ALLOWED_CONTEXT_KEYS = [
        'grant_id',
        'route',
        'reason_code',
        'ip',
        'self_approved',
        'church_slug',
        'organization_subdomain',
        'duration_minutes',
        'expires_at',
    ];

    /**
     * @param  array{
     *     actor_type: string,
     *     actor_id?: int|null,
     *     action: string,
     *     subject_type?: string|null,
     *     subject_id?: int|null,
     *     church_id?: int|null,
     *     organization_id?: int|null,
     *     context?: array<string, mixed>|null
     * }  $data
     */
    public function append(array $data): AccessLedgerEntry
    {
        return DB::transaction(function () use ($data) {
            $last = AccessLedgerEntry::query()
                ->orderByDesc('access_ledger_id')
                ->lockForUpdate()
                ->first();

            $prevHash = $last?->row_hash ?? LedgerHashChain::GENESIS_HASH;
            $createdAtKey = now()->format('Y-m-d H:i:s');

            $payload = [
                'actor_type' => (string) $data['actor_type'],
                'actor_id' => array_key_exists('actor_id', $data) && $data['actor_id'] !== null
                    ? (int) $data['actor_id']
                    : null,
                'action' => (string) $data['action'],
                'subject_type' => $data['subject_type'] ?? null,
                'subject_id' => array_key_exists('subject_id', $data) && $data['subject_id'] !== null
                    ? (int) $data['subject_id']
                    : null,
                'church_id' => array_key_exists('church_id', $data) && $data['church_id'] !== null
                    ? (int) $data['church_id']
                    : null,
                'organization_id' => array_key_exists('organization_id', $data) && $data['organization_id'] !== null
                    ? (int) $data['organization_id']
                    : null,
                'context' => $this->sanitizeContext($data['context'] ?? null),
                'prev_hash' => $prevHash,
                'created_at' => $createdAtKey,
            ];

            $rowHash = LedgerHashChain::hashRow($prevHash, $payload);

            return AccessLedgerEntry::query()->create([
                'actor_type' => $payload['actor_type'],
                'actor_id' => $payload['actor_id'],
                'action' => $payload['action'],
                'subject_type' => $payload['subject_type'],
                'subject_id' => $payload['subject_id'],
                'church_id' => $payload['church_id'],
                'organization_id' => $payload['organization_id'],
                'context' => $payload['context'],
                'prev_hash' => $prevHash,
                'row_hash' => $rowHash,
                'created_at' => $createdAtKey,
            ]);
        });
    }

    public function update(AccessLedgerEntry $entry, array $attributes = []): never
    {
        throw new LogicException('AccessLedgerRepository refuses updates; append a correction row instead.');
    }

    public function delete(AccessLedgerEntry $entry): never
    {
        throw new LogicException('AccessLedgerRepository refuses deletes; the ledger is append-only.');
    }

    /**
     * @return array{ok: bool, broken_at_id: int|null, message: string}
     */
    public function verifyChain(): array
    {
        $expectedPrev = LedgerHashChain::GENESIS_HASH;

        /** @var AccessLedgerEntry $entry */
        foreach (AccessLedgerEntry::query()->orderBy('access_ledger_id')->cursor() as $entry) {
            if ($entry->prev_hash !== $expectedPrev) {
                return [
                    'ok' => false,
                    'broken_at_id' => (int) $entry->access_ledger_id,
                    'message' => "Chain break at access_ledger_id={$entry->access_ledger_id}: prev_hash mismatch.",
                ];
            }

            $payload = [
                'actor_type' => (string) $entry->actor_type,
                'actor_id' => $entry->actor_id !== null ? (int) $entry->actor_id : null,
                'action' => (string) $entry->action,
                'subject_type' => $entry->subject_type,
                'subject_id' => $entry->subject_id !== null ? (int) $entry->subject_id : null,
                'church_id' => $entry->church_id !== null ? (int) $entry->church_id : null,
                'organization_id' => $entry->organization_id !== null ? (int) $entry->organization_id : null,
                'context' => $entry->context,
                'prev_hash' => (string) $entry->prev_hash,
                'created_at' => $entry->created_at?->format('Y-m-d H:i:s'),
            ];

            $expectedHash = LedgerHashChain::hashRow($entry->prev_hash, $payload);
            if (! hash_equals($expectedHash, (string) $entry->row_hash)) {
                return [
                    'ok' => false,
                    'broken_at_id' => (int) $entry->access_ledger_id,
                    'message' => "Chain break at access_ledger_id={$entry->access_ledger_id}: row_hash mismatch.",
                ];
            }

            $expectedPrev = $entry->row_hash;
        }

        return [
            'ok' => true,
            'broken_at_id' => null,
            'message' => 'Access ledger chain intact.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    private function sanitizeContext(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        $clean = [];
        foreach (self::ALLOWED_CONTEXT_KEYS as $key) {
            if (! array_key_exists($key, $context)) {
                continue;
            }
            $value = $context[$key];
            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean === [] ? null : $clean;
    }
}

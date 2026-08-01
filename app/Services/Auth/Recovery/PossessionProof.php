<?php

namespace App\Services\Auth\Recovery;

use App\Models\PossessionProofRecord;
use LogicException;

/**
 * Opaque person-side possession proof. Only RecoveryOtpVerifier may mint instances.
 * CredentialChangeService::complete* requires this type — there is no admin-only overload.
 */
final class PossessionProof
{
    private function __construct(
        private readonly int $possessionProofId,
        private readonly int $userId,
        private readonly string $purpose,
        private readonly string $plaintextToken,
    ) {}

    /**
     * @internal Only RecoveryOtpVerifier may call this.
     */
    public static function mintFromRecord(PossessionProofRecord $record, string $plaintextToken): self
    {
        if ($record->consumed_at !== null) {
            throw new LogicException('Cannot mint PossessionProof from a consumed record.');
        }

        return new self(
            (int) $record->possession_proof_id,
            (int) $record->user_id,
            (string) $record->purpose,
            $plaintextToken,
        );
    }

    public function possessionProofId(): int
    {
        return $this->possessionProofId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function purpose(): string
    {
        return $this->purpose;
    }

    public function plaintextToken(): string
    {
        return $this->plaintextToken;
    }
}

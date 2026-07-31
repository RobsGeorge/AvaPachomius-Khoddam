<?php

namespace App\Contracts;

use App\Models\Organization;

/**
 * Where per-organization isolated DB credentials live.
 * Encrypted columns on organizations for now; swappable for a real vault later.
 */
interface TenantSecretStore
{
    /**
     * @return array{database: string, username: string|null, password: string|null}|null
     */
    public function credentialsFor(Organization $organization): ?array;

    public function store(
        Organization $organization,
        string $database,
        ?string $username,
        ?string $password
    ): void;
}

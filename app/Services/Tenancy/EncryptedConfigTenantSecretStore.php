<?php

namespace App\Services\Tenancy;

use App\Contracts\TenantSecretStore;
use App\Models\Organization;

/**
 * Stores isolated-DB credentials on organizations.db_* (Laravel encrypted cast).
 */
final class EncryptedConfigTenantSecretStore implements TenantSecretStore
{
    public function credentialsFor(Organization $organization): ?array
    {
        if (! filled($organization->db_name)) {
            return null;
        }

        return [
            'database' => (string) $organization->db_name,
            'username' => $organization->db_user,
            'password' => $organization->db_password_encrypted,
        ];
    }

    public function store(
        Organization $organization,
        string $database,
        ?string $username,
        ?string $password
    ): void {
        $organization->forceFill([
            'db_name' => $database,
            'db_user' => $username,
            'db_password_encrypted' => $password,
        ])->save();
    }
}

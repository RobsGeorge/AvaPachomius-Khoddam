<?php

namespace App\Tenancy;

/**
 * Marks a model as living on the repointable {@see TenantDatabaseResolver}
 * tenant connection. While placement is shared, {@see getConnectionName()}
 * returns the app default so sqlite :memory: / production shared path stay a
 * true no-op. When a diocese is db_isolated, queries use the `tenant` connection.
 */
trait BelongsToTenant
{
    public function getConnectionName(): ?string
    {
        return TenantDatabaseResolver::modelConnectionName();
    }
}

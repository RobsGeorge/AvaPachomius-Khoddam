<?php

namespace App\Tenancy;

use App\Contracts\TenantSecretStore;
use App\Models\Church;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves diocese/church placement policy on {@see Organization} and repoints
 * the Laravel `tenant` DB connection when db_isolated. Registry rows always
 * stay on the default (central) connection.
 *
 * Shared placement (the production default, including Tenant Zero) is a no-op:
 * models using {@see BelongsToTenant} keep the app default connection.
 */
final class TenantDatabaseResolver
{
    private static bool $isolated = false;

    /** @var array<string, mixed>|null Snapshot of the pristine tenant connection config. */
    private static ?array $primaryTenantConfig = null;

    public static function modelConnectionName(): string
    {
        return self::$isolated ? 'tenant' : (string) config('database.default');
    }

    public static function usesIsolatedConnection(): bool
    {
        return self::$isolated;
    }

    public static function bindForChurch(?Church $church): void
    {
        if ($church === null) {
            self::bindShared();

            return;
        }

        $placement = self::resolvePlacementOrganization($church);
        if (
            $placement
            && $placement->db_isolated
            && filled($placement->db_name)
        ) {
            self::bindIsolated($placement);

            return;
        }

        self::bindShared();
    }

    /**
     * Placement node for a church:
     * - church_db policy → the church's own organization row
     * - else walk parent_id to a type=diocese ancestor
     * - else the church's own row (top-level / no parent → shared defaults)
     */
    public static function resolvePlacementOrganization(Church $church): ?Organization
    {
        if (! Schema::hasTable('organizations') || ! $church->organization_id) {
            return null;
        }

        $org = Organization::query()->find($church->organization_id);
        if (! $org) {
            return null;
        }

        if ($org->placement_policy === Organization::PLACEMENT_CHURCH_DB) {
            return $org;
        }

        $current = $org;
        for ($guard = 0; $current && $guard < 32; $guard++) {
            if ($current->type === Organization::TYPE_DIOCESE) {
                return $current;
            }
            if (! $current->parent_id) {
                return $current;
            }
            $current = Organization::query()->find($current->parent_id);
        }

        return $org;
    }

    public static function bindIsolated(Organization $placement): void
    {
        self::capturePrimaryTenantConfig();

        $creds = app(TenantSecretStore::class)->credentialsFor($placement);
        $database = $creds['database'] ?? $placement->db_name;
        $username = ($creds['username'] ?? null)
            ?: (self::$primaryTenantConfig['username'] ?? null);
        $password = $creds['password'] ?? '';

        config([
            'database.connections.tenant.database' => $database,
            'database.connections.tenant.username' => $username,
            'database.connections.tenant.password' => $password ?? '',
        ]);

        DB::purge('tenant');
        self::$isolated = true;
    }

    public static function bindShared(): void
    {
        self::capturePrimaryTenantConfig();

        if (self::$primaryTenantConfig !== null) {
            config(['database.connections.tenant' => self::$primaryTenantConfig]);
        }

        DB::purge('tenant');
        self::$isolated = false;
    }

    /** Test / worker cleanup — restore shared defaults and forget snapshots. */
    public static function reset(): void
    {
        if (self::$primaryTenantConfig !== null) {
            config(['database.connections.tenant' => self::$primaryTenantConfig]);
            DB::purge('tenant');
        }
        self::$isolated = false;
        self::$primaryTenantConfig = null;
    }

    private static function capturePrimaryTenantConfig(): void
    {
        if (self::$primaryTenantConfig !== null) {
            return;
        }

        self::$primaryTenantConfig = config('database.connections.tenant');
    }
}

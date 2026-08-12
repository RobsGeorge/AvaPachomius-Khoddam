<?php

namespace App\Services\Documents;

use App\Models\Church;
use App\Models\Organization;
use App\Tenancy\TenantDatabaseResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tenant/organization-scoped document storage.
 *
 * Paths: documents/{placement_organization_id}/{church_id}/{uuid}
 * Isolated dioceses (db_isolated) share the same path convention so a diocese
 * export can scoop its prefix; full isolated root wiring lands with Slice 12.
 */
final class DocumentStorage
{
    public function __construct(
        private DocumentEnvelopeEncryption $encryption,
    ) {}

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('documents.disk', 'local'));
    }

    public function placementOrganization(Church $church): Organization
    {
        return $this->encryption->resolvePlacementOrganization($church);
    }

    /**
     * Relative path under the configured disk for a new object.
     */
    public function makeStorageRef(Church $church, string $extension = 'bin'): string
    {
        $org = $this->placementOrganization($church);
        $prefix = trim((string) config('documents.path_prefix', 'documents'), '/');
        $ext = ltrim($extension, '.');

        return sprintf(
            '%s/%d/%d/%s.%s',
            $prefix,
            (int) $org->organization_id,
            (int) $church->church_id,
            Str::uuid()->toString(),
            $ext !== '' ? $ext : 'bin'
        );
    }

    public function put(string $storageRef, string $contents): void
    {
        $ok = $this->disk()->put($storageRef, $contents);
        if ($ok === false) {
            throw new \RuntimeException('Failed to write document bytes to storage.');
        }
    }

    public function get(string $storageRef): string
    {
        $bytes = $this->disk()->get($storageRef);
        if ($bytes === null) {
            throw new \RuntimeException('Document storage object not found.');
        }

        return $bytes;
    }

    public function exists(string $storageRef): bool
    {
        return $this->disk()->exists($storageRef);
    }

    /**
     * Whether this ref lives under the given placement org prefix
     * (isolated-diocese export completeness check).
     */
    public function isUnderPlacement(string $storageRef, Organization $placement): bool
    {
        $prefix = trim((string) config('documents.path_prefix', 'documents'), '/');
        $expected = $prefix.'/'.(int) $placement->organization_id.'/';

        return str_starts_with($storageRef, $expected);
    }

    /**
     * Placement org for a church — prefers TenantDatabaseResolver diocese ancestor.
     */
    public function resolveIsolatedPlacementHint(Church $church): ?Organization
    {
        $placement = TenantDatabaseResolver::resolvePlacementOrganization($church);
        if ($placement && $placement->db_isolated) {
            return $placement;
        }

        return $placement;
    }
}

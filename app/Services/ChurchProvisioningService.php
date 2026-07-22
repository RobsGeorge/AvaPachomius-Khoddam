<?php

namespace App\Services;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\ChurchUser;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserChurchRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * T4 — create a church tenant end-to-end (row, capabilities, role templates, admins).
 * Subdomain is live on the next request thanks to ResolveTenant + wildcard DNS.
 *
 * P1.1 registry: tenant `church_id` FKs target `organizations.organization_id`.
 * Every provisioned church must therefore get a matching organizations row,
 * numerically aligned (`organization_id` === `church_id`) when possible.
 */
class ChurchProvisioningService
{
    public function __construct(
        private RoleTemplateService $roleTemplates,
    ) {}

    /**
     * @param  array{
     *     slug: string,
     *     name: string,
     *     domain?: string|null,
     *     status?: string,
     *     settings?: array|null,
     *     capabilities?: list<string>|null
     * }  $input
     * @param  list<int>  $adminUserIds
     */
    public function create(array $input, array $adminUserIds = []): Church
    {
        $slug = strtolower(trim($input['slug']));
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ValidationException::withMessages([
                'slug' => __('tenancy.invalid_slug'),
            ]);
        }

        if (Church::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => __('tenancy.slug_taken'),
            ]);
        }

        if ($this->organizationsReady() && Organization::where('subdomain', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => __('tenancy.slug_taken'),
            ]);
        }

        return DB::transaction(function () use ($input, $adminUserIds, $slug) {
            $status = $input['status'] ?? 'active';
            $settings = $input['settings'] ?? null;

            $church = Church::create([
                'slug' => $slug,
                'name' => $input['name'],
                'domain' => $input['domain'] ?? null,
                'status' => $status,
                'settings' => $settings,
                'permissions_version' => 1,
            ]);

            // Roles / capabilities stamp church_id → organizations.organization_id FKs.
            $this->ensureOrganizationLinked($church->fresh());

            $capabilityKeys = $input['capabilities'] ?? array_keys((array) config('capabilities'));
            foreach ($capabilityKeys as $key) {
                if (! array_key_exists($key, (array) config('capabilities'))) {
                    continue;
                }
                ChurchCapability::create([
                    'church_id' => $church->church_id,
                    'capability_key' => $key,
                    'enabled' => true,
                    'config' => null,
                ]);
            }

            $roles = $this->roleTemplates->cloneTemplatesIntoChurch($church->fresh());
            $adminRole = $roles['church-admin'] ?? null;

            foreach (array_unique(array_map('intval', $adminUserIds)) as $userId) {
                if (! User::where('user_id', $userId)->exists()) {
                    continue;
                }

                ChurchUser::firstOrCreate(
                    ['church_id' => $church->church_id, 'user_id' => $userId],
                    ['status' => 'active', 'joined_at' => now()]
                );

                if ($adminRole) {
                    UserChurchRole::firstOrCreate(
                        [
                            'church_id' => $church->church_id,
                            'user_id' => $userId,
                            'role_id' => $adminRole->role_id,
                        ],
                        ['assigned_at' => now()]
                    );
                }
            }

            AuditLogService::recordEvent('church.created', [
                'church_id' => $church->church_id,
                'organization_id' => $church->fresh()->organization_id,
                'slug' => $church->slug,
                'admin_user_ids' => $adminUserIds,
            ]);

            return $church->fresh();
        });
    }

    /**
     * Ensure `organizations` has a row tenants can FK to for this church's church_id.
     * Prefer numerical alignment (organization_id === church_id) per P1.1 expand.
     */
    public function ensureOrganizationLinked(Church $church): ?Organization
    {
        if (! $this->organizationsReady()) {
            return null;
        }

        if ($church->organization_id) {
            $linked = Organization::query()->find($church->organization_id);
            if ($linked) {
                $this->syncOrganizationFromChurch($linked, $church);

                return $linked->fresh();
            }
        }

        $aligned = Organization::query()->find($church->church_id);
        if ($aligned) {
            if ($aligned->subdomain !== $church->slug) {
                throw ValidationException::withMessages([
                    'slug' => __('tenancy.slug_taken'),
                ]);
            }

            $this->syncOrganizationFromChurch($aligned, $church);
            if ((int) $church->organization_id !== (int) $aligned->organization_id) {
                $church->update(['organization_id' => $aligned->organization_id]);
            }

            return $aligned->fresh();
        }

        if (Organization::where('subdomain', $church->slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => __('tenancy.slug_taken'),
            ]);
        }

        $organization = new Organization([
            'parent_id' => null,
            'type' => 'church',
            'subdomain' => $church->slug,
            'name' => $church->name,
            'region' => null,
            'theme' => null,
            'settings' => $church->settings,
            'onboarding_state' => ['phase' => 'provisioned', 'completed' => false],
            'status' => $church->status,
        ]);
        $organization->organization_id = $church->church_id;
        $organization->save();

        $church->update(['organization_id' => $organization->organization_id]);

        return $organization->fresh();
    }

    public function suspend(Church $church): Church
    {
        if ($church->slug === config('tenancy.main_slug')) {
            throw ValidationException::withMessages([
                'status' => __('tenancy.cannot_suspend_main'),
            ]);
        }

        return DB::transaction(function () use ($church) {
            $church->update(['status' => 'suspended']);
            $this->syncLinkedOrganizationStatus($church->fresh(), 'suspended');

            AuditLogService::recordEvent('church.suspended', [
                'church_id' => $church->church_id,
                'slug' => $church->slug,
            ]);

            return $church->fresh();
        });
    }

    public function activate(Church $church): Church
    {
        return DB::transaction(function () use ($church) {
            $church->update(['status' => 'active']);
            $this->syncLinkedOrganizationStatus($church->fresh(), 'active');

            AuditLogService::recordEvent('church.activated', [
                'church_id' => $church->church_id,
                'slug' => $church->slug,
            ]);

            return $church->fresh();
        });
    }

    private function organizationsReady(): bool
    {
        return Schema::hasTable('organizations')
            && Schema::hasColumn('church', 'organization_id');
    }

    private function syncOrganizationFromChurch(Organization $organization, Church $church): void
    {
        $organization->fill([
            'type' => 'church',
            'subdomain' => $church->slug,
            'name' => $church->name,
            'settings' => $church->settings,
            'status' => $church->status,
        ]);
        $organization->save();
    }

    private function syncLinkedOrganizationStatus(Church $church, string $status): void
    {
        if (! $this->organizationsReady() || ! $church->organization_id) {
            return;
        }

        Organization::query()
            ->where('organization_id', $church->organization_id)
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    /**
     * @param  list<string>  $enabledKeys
     */
    public function syncCapabilities(Church $church, array $enabledKeys): void
    {
        $catalog = array_keys((array) config('capabilities'));
        $enabled = collect($enabledKeys)->intersect($catalog)->values();

        foreach ($catalog as $key) {
            $row = ChurchCapability::firstOrNew([
                'church_id' => $church->church_id,
                'capability_key' => $key,
            ]);
            $row->enabled = $enabled->contains($key);
            if (! $row->exists) {
                $row->config = null;
            }
            $row->save();
        }

        app(CoursePermissionResolver::class)->bumpChurchPermissionsVersion($church->fresh());

        AuditLogService::recordEvent('church.capabilities_synced', [
            'church_id' => $church->church_id,
            'enabled' => $enabled->all(),
        ]);
    }

    public function addMember(Church $church, User $user, ?Role $role = null): ChurchUser
    {
        $membership = ChurchUser::firstOrCreate(
            ['church_id' => $church->church_id, 'user_id' => $user->user_id],
            ['status' => 'active', 'joined_at' => now()]
        );

        if ($membership->status !== 'active') {
            $membership->update(['status' => 'active', 'joined_at' => $membership->joined_at ?? now()]);
        }

        if ($role && (int) $role->church_id === (int) $church->church_id) {
            UserChurchRole::firstOrCreate(
                [
                    'church_id' => $church->church_id,
                    'user_id' => $user->user_id,
                    'role_id' => $role->role_id,
                ],
                ['assigned_at' => now()]
            );
        }

        AuditLogService::recordEvent('church.member_added', [
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $role?->role_id,
        ]);

        return $membership->fresh();
    }

    public function removeMember(Church $church, User $user): void
    {
        if ($church->slug === config('tenancy.main_slug') && $user->is_superadmin) {
            // Superadmins may keep main membership; still allow remove of others.
        }

        ChurchUser::where('church_id', $church->church_id)
            ->where('user_id', $user->user_id)
            ->delete();

        UserChurchRole::where('church_id', $church->church_id)
            ->where('user_id', $user->user_id)
            ->delete();

        AuditLogService::recordEvent('church.member_removed', [
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
        ]);
    }
}

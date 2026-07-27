<?php

namespace App\Support;

use App\Models\User;
use App\Services\ImpersonationService;
use App\Services\PlatformAccessService;
use App\Services\RolePreviewService;
use App\Tenancy\TenantContext;

/**
 * When superadmin sees member workspace chrome vs platform-only Console.
 */
final class SuperadminWorkspace
{
    public static function isSuperadmin(?User $user): bool
    {
        return $user instanceof User && ($user->is_superadmin ?? false);
    }

    /** View-as, platform-enter, or impersonation (target session). */
    public static function inTenantWorkflow(): bool
    {
        return ImpersonationService::isActive()
            || RolePreviewService::isActive()
            || PlatformAccessService::isActive();
    }

    /**
     * Academic / Service / System hubs and workspace switchers for superadmin.
     * Members always see member chrome; superadmin only after entering a church workflow.
     */
    public static function showsMemberChrome(?User $user): bool
    {
        if (! self::isSuperadmin($user)) {
            return true;
        }

        if (! config('tenancy.enabled')) {
            return true;
        }

        if (ImpersonationService::isActive()) {
            return true;
        }

        return self::inTenantWorkflow() && TenantContext::current() !== null;
    }

    public static function showsWorkspaceBar(?User $user): bool
    {
        return self::showsMemberChrome($user);
    }

    /** Superadmin nav: platform-only tiles (no shared member hubs). */
    public static function showsPlatformOnlyNav(?User $user): bool
    {
        if (! self::isSuperadmin($user) || ! config('tenancy.enabled')) {
            return false;
        }

        if (ImpersonationService::isActive()) {
            return false;
        }

        return ! self::inTenantWorkflow() || ChurchHost::isConsoleHost();
    }

    /**
     * Console host with no tenant bound: structure CRUD must name a church explicitly.
     */
    public static function requiresExplicitChurchScope(): bool
    {
        return config('tenancy.enabled')
            && ChurchHost::isConsoleHost()
            && ! TenantContext::enforced();
    }
}

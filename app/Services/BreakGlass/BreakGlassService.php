<?php

namespace App\Services\BreakGlass;

use App\Models\AccessLedgerEntry;
use App\Models\BreakGlassGrant;
use App\Models\Church;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccessLedger\AccessLedgerRepository;
use App\Services\AuditLogService;
use App\Tenancy\TenantDatabaseResolver;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Time-boxed staff access to church/org prod data. Fail-closed on any error.
 */
final class BreakGlassService
{
    public function __construct(
        private AccessLedgerRepository $ledger,
    ) {}

    public function placementOrganizationForChurch(Church $church): ?Organization
    {
        return TenantDatabaseResolver::resolvePlacementOrganization($church);
    }

    /**
     * Active unexpired grant for staff + organization, or null.
     * Fail-closed: any exception → null (deny).
     */
    public function activeGrant(User $staff, Organization $organization): ?BreakGlassGrant
    {
        try {
            return BreakGlassGrant::query()
                ->forStaff((int) $staff->user_id)
                ->forOrganization((int) $organization->organization_id)
                ->active()
                ->orderByDesc('expires_at')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Break-glass grant lookup failed closed', [
                'error' => $e->getMessage(),
                'staff_id' => $staff->user_id ?? null,
                'organization_id' => $organization->organization_id ?? null,
            ]);

            return null;
        }
    }

    /**
     * Assert staff may open prod data for the church's placement organization.
     * On success writes break_glass_open to the access ledger (same path for self-approved).
     *
     * @throws HttpException
     */
    public function assertAllowed(User $staff, Church $church): BreakGlassGrant
    {
        try {
            if (! ($staff->is_superadmin ?? false)) {
                abort(403, __('workspace.break_glass_denied'));
            }

            $organization = $this->placementOrganizationForChurch($church);
            if (! $organization) {
                abort(403, __('workspace.break_glass_no_organization'));
            }

            $grant = $this->activeGrant($staff, $organization);
            if (! $grant) {
                abort(403, __('workspace.break_glass_denied'));
            }

            $this->ledger->append([
                'actor_type' => AccessLedgerEntry::ACTOR_STAFF,
                'actor_id' => (int) $staff->user_id,
                'action' => AccessLedgerEntry::ACTION_BREAK_GLASS_OPEN,
                'subject_type' => 'break_glass_grant',
                'subject_id' => (int) $grant->break_glass_grant_id,
                'church_id' => (int) $church->church_id,
                'organization_id' => (int) $organization->organization_id,
                'context' => [
                    'grant_id' => (int) $grant->break_glass_grant_id,
                    'self_approved' => (bool) $grant->self_approved,
                    'church_slug' => $church->slug,
                    'organization_subdomain' => $organization->subdomain,
                    'expires_at' => $grant->expires_at?->toIso8601String(),
                ],
            ]);

            return $grant;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Break-glass assert failed closed', ['error' => $e->getMessage()]);
            abort(403, __('workspace.break_glass_denied'));
        }
    }

    /**
     * Create a grant. Self-approval when staff_id equals granter (solo-builder path)
     * is logged identically — no silent special case on use.
     */
    public function grant(
        User $granter,
        User $staff,
        Organization $organization,
        string $reason,
        int $durationMinutes,
    ): BreakGlassGrant {
        abort_unless($granter->is_superadmin ?? false, 403);

        $durationMinutes = max(1, min($durationMinutes, 60 * 24 * 7));
        $now = now();
        $selfApproved = (int) $granter->user_id === (int) $staff->user_id;

        $grant = BreakGlassGrant::query()->create([
            'staff_id' => (int) $staff->user_id,
            'organization_id' => (int) $organization->organization_id,
            'reason' => $reason,
            'granted_at' => $now,
            'expires_at' => $now->copy()->addMinutes($durationMinutes),
            'self_approved' => $selfApproved,
            'created_at' => $now,
        ]);

        AuditLogService::recordEvent('break_glass.grant_created', [
            'grant_id' => (int) $grant->break_glass_grant_id,
            'staff_id' => (int) $staff->user_id,
            'organization_id' => (int) $organization->organization_id,
            'self_approved' => $selfApproved,
            'duration_minutes' => $durationMinutes,
            'expires_at' => $grant->expires_at?->toIso8601String(),
        ]);

        return $grant;
    }

    /** End a grant early by setting expires_at to now (not a ledger rewrite). */
    public function revoke(User $actor, BreakGlassGrant $grant): void
    {
        abort_unless($actor->is_superadmin ?? false, 403);

        if (! $grant->isActive()) {
            return;
        }

        $grant->forceFill(['expires_at' => now()])->save();

        AuditLogService::recordEvent('break_glass.grant_revoked', [
            'grant_id' => (int) $grant->break_glass_grant_id,
            'staff_id' => (int) $grant->staff_id,
            'organization_id' => (int) $grant->organization_id,
        ]);
    }
}

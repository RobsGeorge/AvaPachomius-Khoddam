# P5 — Provisioning & customization UI

**Goal:** let the superadmin create and configure a subsidiary end-to-end from the console
(no SQL, no deploy), and let subsidiary admins self-manage their own space within guardrails.

**In scope:** `TenantProvisioningService`, superadmin console screens (subsidiary CRUD, capability
toggles, member management, role matrix, branding), subsidiary-admin self-service (scoped),
invitation flow, branding resolution.
**Prereq:** P1–P4.

---

## 1. `TenantProvisioningService` (the "Create Subsidiary" action)

Transactional — creates the tenant, enables its product, clones roles, links admins, audits.

```php
class TenantProvisioningService
{
    public function create(array $input, array $adminUserIds): Subsidiary
    {
        return DB::transaction(function () use ($input, $adminUserIds) {
            $sub = Subsidiary::create([
                'slug' => $input['slug'], 'name' => $input['name'], 'status' => 'active',
                'domain' => $input['domain'] ?? null, 'settings' => $input['branding'] ?? null,
            ]);

            foreach ($input['capabilities'] as $key => $config) {          // P2
                SubsidiaryCapability::create([
                    'subsidiary_id' => $sub->subsidiary_id,
                    'capability_key' => $key, 'enabled' => true, 'config' => $config ?: null,
                ]);
            }

            $roles = app(RoleTemplateService::class)->cloneInto($sub);     // P3

            foreach ($adminUserIds as $userId) {
                SubsidiaryUser::firstOrCreate(
                    ['subsidiary_id' => $sub->subsidiary_id, 'user_id' => $userId],
                    ['status' => 'active', 'joined_at' => now()],
                );
                UserCourseRole::create([
                    'subsidiary_id' => $sub->subsidiary_id, 'user_id' => $userId,
                    'role_id' => $roles['admin']->role_id, 'course_id' => null, // subsidiary-wide
                ]);
            }

            AuditLogService::record('subsidiary.created', $sub);
            return $sub;
        });
    }
}
```

The subdomain (`{slug}.inst.org`) is **live immediately** thanks to P4's wildcard DNS/TLS + DB
resolution. The new admins log in there; the membership gate admits them; the global scope
auto-stamps everything they create.

## 2. Superadmin console screens (`admin.inst.org`)

- **Subsidiaries** — list / create (the form feeding `TenantProvisioningService`) / edit / suspend
  / archive. Suspending flips `status` → subdomain 404s (P1 guard).
- **Capabilities** — per subsidiary, toggle each capability + edit its config (attendance mode,
  thresholds, `recurring_years`, `modules`). Saves to `subsidiary_capability`; busts caps cache.
- **Members** — add/remove/suspend `subsidiary_user`; invite by email (see §4).
- **Roles & permissions matrix** — the P3 roles × permissions grid; superadmin can edit any
  subsidiary's; bumps `permissions_version`.
- **Branding** — name, logo upload, theme palette, default locale → `subsidiary.settings` JSON.
- **Audit** — reuse existing `SuperAdminAuditController`, now filterable by subsidiary.

## 3. Subsidiary-admin self-service (scoped, in-subdomain)

A subsidiary admin (holding `role.manage` / `subsidiary.members.manage`) manages **only their own**
subsidiary from within `{slug}.inst.org`:
- Members (invite/assign roles), roles & permission matrix (bounded by enabled capabilities +
  guardrails from P3), capability **config** (not enabling new capabilities — that's superadmin),
  and their own branding.
- All actions pass `RolePermissionPolicy` + audit. Platform-level controls never appear for them.

## 4. Invitation / onboarding flow (reuse existing auth)

- Invite by email → if the email is **new**, create a `user` (unverified) + `subsidiary_user`
  (`status=invited`); if it **already exists** globally, just add the membership (shared pool — no
  duplicate account, no email-unique error).
- Reuse `PendingRegistrationService` + the existing `/set-password/{user_id}` flow for new users.
- On first login at the subdomain, `invited` → `active`.
- This is the concrete handling of "a person joins a second subsidiary": a new membership row, not
  a new identity.

## 5. Branding resolution

`IdentifySubsidiary` reads `subsidiary.settings` and shares to views: logo, theme palette, default
locale. Integrate with the existing `ThemeController` and locale infra (`SetLocale`,
`config/translation.php`) so each subdomain renders its own identity. Fall back to platform
defaults when unset.

## Acceptance criteria

- Superadmin creates a fully working subsidiary from the UI: subdomain live, capabilities set,
  roles seeded, admins linked — without SQL or deploy.
- New admins immediately manage and create isolated content on their subdomain.
- Subsidiary admins self-serve within guardrails; cannot escalate beyond enabled capabilities or
  their own permissions, cannot touch other subsidiaries.
- Inviting an existing-email user adds a membership rather than failing on unique email.
- Each subdomain shows its own branding.

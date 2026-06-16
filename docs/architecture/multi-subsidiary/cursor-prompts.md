# Cursor Prompts — Strict, Phase-by-Phase

Copy-paste prompts to drive Cursor. **Feed the STANDING RULES once** (pin it), then run one PHASE
prompt at a time, in order. Do not start a phase until the previous one's acceptance criteria pass.

---

## STANDING RULES (pin this in Cursor; applies to every prompt)

```
You are implementing a multi-subsidiary architecture in an existing Laravel 10 / PHP 8.2 app
(MySQL, brownfield schema). The authoritative spec lives in docs/architecture/multi-subsidiary/.
Before writing code for a phase, READ its phase file (P0..P6), plus database-guide.md,
routes-and-apis.md, and ui-ux.md.

HARD RULES — violating any of these is a failed task:
1. Do NOT change behavior outside the current phase's scope. Each phase must leave the app working.
2. Migrations:
   - New tables → App\Database\SchemaGuards::createTableIfMissing() with id('x_id') PK convention.
   - Columns on existing tables → App\Database\MigrationSupport (addColumn/addStringColumn/...).
   - Type/NOT NULL changes → raw DB::statement('ALTER TABLE ... MODIFY ...') guarded by
     Schema::hasColumn + driver === 'mysql', only AFTER backfill is verified.
   - Every migration MUST be idempotent and safe to re-run on a live DB that already has data.
   - NEVER drop/rename a legacy column or destroy data in up(). down() may drop only what you added.
3. Respect existing conventions: non-standard PKs (user_id, course_id, role_id, ...),
   user.$timestamps=false, the helpers in app/helpers.php, the SafeMySqlConnection layer.
4. All user-facing strings via __() ; add AR + EN keys. Keep RTL + theme-aware UI (Bootstrap 5,
   Bootstrap Icons, Alpine.js) matching layouts/navigation.blade.php.
5. Do NOT introduce new packages (no spatie/laravel-permission). Use the custom tables in the spec.
6. The current live platform = the FIRST subsidiary. Read its slug from config('tenancy.main_slug').
   Never hardcode 'main'.
7. After each phase: run `php artisan migrate` on a copy, run the acceptance checks in the phase
   file, and report results. Write tests where the phase file asks.

OUTPUT for every phase: list files created/modified, the migration filenames, how you verified
acceptance, and any deviation from the spec (with reason).
```

---

## PHASE P0 — Foundation

```
Implement P0 exactly as docs/architecture/multi-subsidiary/P0-foundation.md.

Create:
- config/tenancy.php (main_slug, console_host, tenant_tables) as specified.
- Migration create_subsidiary_tables (subsidiary + subsidiary_user) via SchemaGuards.
- Migration add_subsidiary_id_to_tenant_tables (nullable + index, loop over config tenant_tables,
  idempotent, information_schema index check — NOT Doctrine).
- Migration seed_main_and_backfill_subsidiary (idempotent: firstOrCreate subsidiary from
  config('tenancy.main_slug')/TENANCY_MAIN_NAME; UPDATE ... WHERE subsidiary_id IS NULL on each
  tenant table; chunked subsidiary_user backfill with insertOrIgnore).
- Models App\Models\Subsidiary and App\Models\SubsidiaryUser exactly per spec.
- Add subsidiaries()/memberships()/belongsToSubsidiary() to App\Models\User.

DO NOT: add any global scope, middleware, NOT NULL, FK, or touch roles/user_course_role/auth/nav.

ACCEPTANCE: `php artisan migrate` then re-run = no error; every tenant table row has subsidiary_id
set to the first subsidiary; every user has one subsidiary_user row; the app's existing pages are
byte-for-byte unchanged; `migrate:rollback` cleanly drops the new tables/column. Add a feature test
asserting the backfill counts (zero NULLs, one membership per user).
```

## PHASE P1 — Scoping & resolution

```
Implement P1 exactly as docs/architecture/multi-subsidiary/P1-scoping-resolution.md.

Create:
- app/Support/Tenancy.php + current_subsidiary() helper in app/helpers.php.
- Trait App\Models\Concerns\BelongsToSubsidiary (global scope reading Tenancy::enforced()/id();
  creating() auto-stamp). Apply to: Course, Module, Content, Assignment, Session, Exam, Asessment,
  CourseAssessment, Attendance, GradeCategory, ActivityLog. Audit any child model queried directly
  and report it (do not silently skip).
- Middleware IdentifySubsidiary (console_host early return; resolve domain→slug→main; abort 404 on
  unknown/suspended; Tenancy::set + view share). Register in the web group AFTER SetLocale, BEFORE
  LogUserActivity, in app/Http/Kernel.php.
- Middleware EnsureSubsidiaryMember + alias 'subsidiary.member'. Add it to authenticated route
  groups. Enforce membership at login (superadmin exempt) in the login controller.
- Migration enforcing subsidiary_id NOT NULL on tenant tables (guarded, mysql-only, after verifying
  zero NULLs). Optional FK only if zero orphans.
- Update ImpersonationService to set the impersonated user's subsidiary context and restore on stop.

In P1 there are no subdomains yet — every host falls back to the first subsidiary, so user-visible
behavior must NOT change.

ACCEPTANCE: scoped models return only current-subsidiary rows; creating auto-stamps subsidiary_id;
withoutGlobalScope('subsidiary') returns all; a non-member is rejected; impersonation respects the
target subsidiary. Add tests: seed TWO subsidiaries, assert cross-tenant READ and WRITE both fail,
and assert the first-subsidiary user retains full access.
```

## PHASE P2 — Capabilities

```
Implement P2 exactly as docs/architecture/multi-subsidiary/P2-capabilities.md.

Create:
- config/capabilities.php catalog (attendance/exams/curriculum/assignments/grades/assessments/
  reporting) with label, permissions[], config defaults.
- Migration create_subsidiary_capability_table.
- Model SubsidiaryCapability; add hasCapability()/capabilityConfig()/enabledCapabilities()
  (per-request cache key sub:{id}:caps) + capabilities() relation to Subsidiary.
- Middleware RequireCapability + alias 'capability'. Wrap the EXISTING route groups in
  routes/web.php (exams, attendance, curriculum/modules/lectures, assignments, grades, assessments,
  reporting) with capability:<key>. Do NOT change their role checks yet (that's P3).
- Blade::if('capability', ...) directive. Refactor layouts/navigation.blade.php (both desktop and
  d-md-none blocks) to gate feature links with @capability; keep manage/view distinction.
- Make AttendanceController/GraduationService read thresholds from capabilityConfig('attendance')
  with the course value as override.
- Seed: enable ALL capabilities for the first subsidiary (preserve current behavior).
- Cache-bust sub:{id}:caps whenever capabilities change.

ACCEPTANCE: disabling a capability 404s its routes and hides its nav for that subsidiary only;
first subsidiary has all capabilities on → unchanged behavior; attendance uses config. Add a test
toggling a capability and asserting route 404 + nav absence.
```

## PHASE P3 — Roles & permissions

```
Implement P3 exactly as docs/architecture/multi-subsidiary/P3-roles-permissions.md.

Create/evolve:
- Migrations: add subsidiary_id+slug+is_system to roles (backfill to first subsidiary, slug from
  role_name, then UNIQUE(subsidiary_id,slug)); create permissions + role_permission; add
  subsidiary_id to user_course_role and MODIFY course_id NULLABLE (backfill subsidiary_id from
  course, fallback first subsidiary, then NOT NULL); add permissions_version to subsidiary.
- config/permissions.php catalog (key => capability_key); platform perms have capability_key=null.
- Seeders: upsert permissions; create platform template roles (subsidiary_id=null) + default grants.
- Models: Permission; Role evolves (subsidiary, slug, is_system, permissions()); role_permission.
- Service RoleTemplateService::cloneInto($subsidiary) limited to enabled capabilities.
- User::permissionsIn($sub) (cached with permissions_version; superadmin = all) and
  canInSubsidiary($perm). Gate::before superadmin=true; Gate::define every permission key.
- Middleware RequirePermission + alias 'permission'. Replace every role:/attendance.staff/admin
  role-name check across routes/web.php, AdminMiddleware, AttendanceStaffMiddleware, and Blade
  (@can) per the mapping table in the phase file. Keep 'superadmin' unchanged. Retire RoleMiddleware.
- Cache invalidation: bump subsidiary.permissions_version on any grant/role/capability change.
- RolePermissionPolicy guardrails (grant only perms you hold; only enabled-capability perms;
  platform perms superadmin-only; refuse removing last admin/role.manage grant).
- RolePermissionController (matrix index/store/update/destroy), audited.

ACCEPTANCE: a user holds different roles in different subsidiaries; subsidiary-specific + custom
roles work with no code change; permission gates enforce; superadmin bypasses; toggling a grant
takes effect after version bump; guardrails block escalation/lockout; all former role checks
migrated and first-subsidiary users keep equivalent access. Provide a route-by-route audit list of
every role:/hasAnyRole occurrence and its new permission mapping.
```

## PHASE P4 — Subdomains live

```
Implement P4 exactly as docs/architecture/multi-subsidiary/P4-subdomains-live.md AND coordinate
server steps in server-walkthrough.md (the human performs DNS/TLS — you do code/config).

Code/config:
- Enable App\Http\Middleware\TrustHosts in Kernel global middleware.
- Add the sessions table migration (idempotent, project PK conventions) for SESSION_DRIVER=database.
- Add the console-host route group (Route::domain(config('tenancy.console_host')) ...) and move/alias
  the existing /superadmin routes there; ensure IdentifySubsidiary early-returns for the console host.
- Make IdentifySubsidiary resolve real subdomains + custom domains (already coded in P1 — verify).
- Login: enforce membership across subdomains; add subsidiary.switch route + switcher UI.
- Update .env.example and .github/DEPLOY-VPS.md with SESSION_DRIVER=database, SESSION_DOMAIN,
  APP_URL, console host, wildcard notes.

DO NOT perform DNS/TLS/server changes — output the exact commands for the human (see walkthrough).

ACCEPTANCE: with wildcard DNS/TLS in place, slug.<domain> resolves the right subsidiary; SSO works
across subdomains but membership still gates access; admin.<domain> serves the console cross-tenant;
ForceLogoutService/flush-all works across subdomains on DB sessions.
```

## PHASE P5 — Provisioning & customization UI

```
Implement P5 exactly as docs/architecture/multi-subsidiary/P5-provisioning-customization.md and the
console/self-service screens in ui-ux.md and routes-and-apis.md.

Create:
- TenantProvisioningService::create() (transactional: subsidiary + capabilities + RoleTemplateService
  clone + memberships + admin grants + audit).
- Console\* controllers + routes (subsidiaries CRUD/suspend/archive, capabilities update, members,
  roles matrix, branding, audit filter) on the console host.
- Manage\* controllers + routes (in-subdomain self-service: members, roles matrix, capability CONFIG
  only, branding) bound to Tenancy::current(), behind RolePermissionPolicy.
- Branding resolution in IdentifySubsidiary → share logo/theme/locale; navbar renders subsidiary
  identity. Invitation flow reuses PendingRegistrationService + /set-password.

ACCEPTANCE: superadmin creates a fully working subsidiary from the UI (subdomain live, capabilities
set, roles seeded, admins linked) with no SQL/deploy; subsidiary admins self-serve within guardrails;
inviting an existing email adds a membership (no unique-email error); each subdomain shows its brand.
```

## PHASE P6 — Pilot

```
Follow docs/architecture/multi-subsidiary/P6-pilot.md. Onboard a contrasting second subsidiary
(Service tenant: attendance lenient, no exams/grades, roles servant/خادم + served/مخدوم, no student;
OR reporting-only). Run the full validation checklist (isolation read+write, capability gating,
custom roles, cross-subsidiary identity + SSO, branding, provisioning self-service, audit,
force-logout). Produce a runbook and the EXPLAIN results proving subsidiary_id indexes are used on
the hot queries. Run the repo's security review over the tenancy + permission code.
```

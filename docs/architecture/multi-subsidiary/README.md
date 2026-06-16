# Multi-Subsidiary Architecture — Master Spec

> Status: **design / refinement phase** (no code written yet). This directory is the
> authoritative specification for evolving the portal from a single-institution system
> into a multi-subsidiary platform on one shared database and one codebase.

## Positioning

**The current live platform becomes the first subsidiary** under a larger multi-disciplinary
umbrella. All existing data is backfilled into it; the umbrella is the superadmin/console layer
(`admin.<domain>`), not a data row. Set its real slug/name via `TENANCY_MAIN_SLUG` /
`TENANCY_MAIN_NAME` — never leave it as the literal "main".

## The product goal

One institution, many **subsidiaries** (branches / entities / services), each with
**different product requirements**:

- Not all have exams or attendance.
- Attendance rules differ (strict / lenient / none).
- Some are reporting-only tools.
- Some recur yearly without modules.
- Roles differ per subsidiary (e.g. one has *students*, another has *servants/خدّام* and *served/مخدومين*, not students).

All on **one shared MySQL database** and **one codebase**, subsidiaries separated by **subdomain**.

## The core reframe (read this first)

"Separation between subsidiaries" is **two independent problems**:

1. **Data separation** — `subsidiary_id` on every tenant table + a `BelongsToSubsidiary`
   global scope + a membership gate at login. This is the *real* security boundary, and it is
   **identical regardless of the URL strategy**.
2. **Request→subsidiary resolution + branding** — how an HTTP request maps to a subsidiary.
   This is the *only* thing the subdomain-vs-routes choice affects.

Decide data + capability models properly; treat the URL as a thin resolution layer on top.

## Locked decisions

| Decision | Choice | Rationale |
|---|---|---|
| URL strategy | **Subdomain** `{slug}.inst.org` (+ optional custom `domain`) | Leaves the existing ~80 routes and every `route()` call untouched; real per-subsidiary identity; future custom domains free. Path-prefix would force rewriting every route. |
| User identity | **Shared global pool + `subsidiary_user` membership** | One login, member of many subsidiaries; `user.email` stays globally unique; enables SSO + superadmin oversight. |
| Data isolation | **Hard** (global Eloquent scope; only superadmin bypasses) | A subsidiary admin can never see another subsidiary's data. |
| Permission layer | **Extend the custom tables** (not spatie) | Zero friction with the brownfield / `SafeMySql` schema; full control. |
| Roles | **Per-subsidiary** (`roles.subsidiary_id`) + platform templates | Each subsidiary owns its role set; custom roles (servant) need no code because permissions are the contract. |
| Dynamic permissions | **Dynamic role→permission grants** (editable matrix), permission *keys* stay code-defined | Admins toggle grants live, no deploy; keys can't be misconfigured into a lockout. |

## Hard constraints from the existing codebase

- **Brownfield / legacy schema.** Migrations adding columns to *existing* tables MUST be
  idempotent via `App\Database\MigrationSupport` / `SchemaGuards` (see `LegacySchemaSync`).
  Brand-new tables may use plain `Schema::create` with the `id('x_id')` PK convention.
- **Non-standard primary keys** (`user_id`, `course_id`, `role_id`, …). `user` table uses
  `protected $primaryKey = 'user_id'` and `$timestamps = false`.
- **`SafeMySqlConnection` / `SafeMySqlSchemaBuilder`** wrap the connection — avoid raw Doctrine
  assumptions; prefer `information_schema` checks where `LegacySchemaSync` already does.
- **Auth is custom** (OTP, `is_verified`, `PendingRegistrationService`, `is_superadmin`).
- **Session driver is `file`** — must move to `database` before subdomains go live (P4), or
  cross-subdomain SSO + `ForceLogoutService` + audit won't work.
- **Role checks today are global** (`RoleMiddleware`, `hasAnyRole`) — they ignore the
  per-course scope already in `user_course_role`. P3 makes checks subsidiary-contextual.
- **Subdomain scaffolding exists but disabled**: `TrustHosts` is commented out in
  `app/Http/Kernel.php`; `SESSION_DOMAIN` is env-driven.

## Three-layer authorization model

```
CAPABILITY   what features exist for this subsidiary   (P2)  → disabled ⇒ 404
   ▲ ceiling
PERMISSION   who may do what, within enabled features  (P3)  → not granted ⇒ 403
   ▲ contract (code-defined keys)
ROLE+GRANT   bundles of permissions, assigned to users (P3)  → fully dynamic, per subsidiary
```

Resolution order on every request: superadmin → capability enabled? → permission granted in
current subsidiary (subsidiary-wide grant or the course in scope)? → allow / deny.

## Phase index

| Phase | Title | Behavior change | File |
|---|---|---|---|
| **P0** | Foundation: tenant tables + backfill | None | [P0-foundation.md](P0-foundation.md) |
| **P1** | Scoping & resolution (global scope, middleware, membership gate) | Isolation enforced (still single implicit tenant) | [P1-scoping-resolution.md](P1-scoping-resolution.md) |
| **P2** | Capabilities (per-subsidiary feature switches) | Features become toggleable | [P2-capabilities.md](P2-capabilities.md) |
| **P3** | Roles & permissions (dynamic, per-subsidiary) | Role checks become permission+context checks | [P3-roles-permissions.md](P3-roles-permissions.md) |
| **P4** | Subdomains live (DNS/TLS, sessions, console) | Real subdomains + SSO | [P4-subdomains-live.md](P4-subdomains-live.md) |
| **P5** | Provisioning & customization UI | Superadmin self-service | [P5-provisioning-customization.md](P5-provisioning-customization.md) |
| **P6** | Pilot: onboard subsidiary #2 | Second tenant, different product | [P6-pilot.md](P6-pilot.md) |

Each phase is independently shippable and ordered so the app keeps working at every step.

## Implementation guides (cross-phase references)

| Guide | Use for |
|---|---|
| [database-guide.md](database-guide.md) | Exact migration inventory, legacy-schema rules, backfill + verification SQL, indexes, rollback |
| [routes-and-apis.md](routes-and-apis.md) | Host model, middleware stacks, every new/changed route + name + controller, API surface |
| [ui-ux.md](ui-ux.md) | Screen-by-screen UI/UX, console + self-service, role matrix, branding, RTL/Arabic rules |
| [cursor-prompts.md](cursor-prompts.md) | Standing rules + one strict, copy-paste prompt per phase to drive Cursor |
| [server-walkthrough.md](server-walkthrough.md) | 🤖 Cursor vs 🧑 You steps; DNS/TLS/nginx/session changes (concentrated in P4) |
| [testing-prompts.md](testing-prompts.md) | Per-phase test prompts (unit + e2e + load), report format, no-commit-before-review rule |
| [testing-pipeline.md](testing-pipeline.md) | One strict prompt to build CI (PHPUnit + Dusk + k6) and gate deploy on green |

**Build order, per phase:** (1) pin the STANDING RULES from `cursor-prompts.md`; (2) run the phase's
build prompt; (3) run the phase's **testing prompt** from `testing-prompts.md` and review the
generated `test-reports/P{n}-report.md`; (4) only then commit; (5) run the matching server steps from
`server-walkthrough.md`. Set up `testing-pipeline.md`'s CI **before P0** as the baseline gate.

## Cross-cutting concerns (apply to every phase)

- **Idempotent migrations** — every schema change must survive a re-run on the legacy VPS DB.
- **Indexes** — `subsidiary_id` is in nearly every WHERE after P1; index it on every tenant table.
- **Caching** — effective-permissions resolved once per request, cached with a version stamp
  bumped on grant changes (P3).
- **Audit** — provisioning, capability/permission changes, and impersonation flow through
  `AuditLogService`.
- **Impersonation** — `ImpersonationService` must set the impersonated user's subsidiary context
  so the global scope follows the impersonated identity.
- **Testing** — each phase ships with isolation tests (cross-tenant read/write must fail) and a
  "main behaves exactly as before" regression check.

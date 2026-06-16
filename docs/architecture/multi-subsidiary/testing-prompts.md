# Testing Prompts — Per-Phase (Cursor)

Every phase ships with tests **and a test report that a human reviews before the phase is
committed**. Run the matching block right after the phase's implementation prompt, in the same
branch, but **do not commit until the report is green and reviewed**.

Tooling (dev-only — exempt from the app's "no new packages" rule, but must stay in `require-dev` /
out of the runtime):
- **Unit + Feature:** PHPUnit 10 (already present; `tests/Unit`, `tests/Feature`, `RefreshDatabase`).
- **E2E:** **Laravel Dusk** (stays in the PHP/composer ecosystem, drives the real Blade+Alpine UI).
  Playwright is an acceptable alternative if a Node toolchain is preferred.
- **Load:** **k6** (standalone binary; scripted concurrency + thresholds). Scripts live in
  `tests/Load/`.

---

## STANDING TESTING RULES (pin alongside the build STANDING RULES)

```
- Test DB: a real MySQL test database (the app's legacy schema + LegacySchemaSync run on migrate;
  sqlite :memory: may not reproduce the brownfield ALTERs). Use RefreshDatabase.
- Tenant context in tests: the global scope reads App\Support\Tenancy. Provide a TenancyTestCase
  base with helpers:
      seedSubsidiary(string $slug): Subsidiary
      actingInSubsidiary(Subsidiary $s): void   // App\Support\Tenancy::set($s)
      actingAsMember(User $u, Subsidiary $s, string $roleSlug = null)
  Feature tests that hit routes go through IdentifySubsidiary by setting the Host header
  (->withHeader('Host', "{$slug}.inst.test")) so resolution + membership run for real.
- EVERY phase's tests MUST include the isolation invariant where applicable: seed TWO subsidiaries
  and assert cross-tenant READ and WRITE both fail.
- Each phase produces docs/architecture/multi-subsidiary/test-reports/P{n}-report.md (format below).
- DO NOT commit a phase until: all unit+feature green, e2e green, load thresholds met (where the
  phase has a load target), and the report is reviewed/approved by the human.
- No flaky tests: no real sleeps, no external network, deterministic seeds.
```

### Report format — `test-reports/P{n}-report.md`
```
# P{n} Test Report — <date>
## Summary: PASS/FAIL · unit X/X · feature X/X · e2e X/X · load: <met/not met>
## Coverage: <% lines for new app/ files in this phase>  (target ≥ 80% for new code)
## Unit + Feature: <suite output summary; list each new test + assertion>
## Isolation invariant: <cross-tenant read fail? write fail? withoutGlobalScope sees all?>
## E2E: <scenarios run, screenshots/links, pass/fail>
## Load (if applicable): <VUs, RPS, p95 latency, error rate, index usage / EXPLAIN notes>
## Deviations / known gaps: <…>
## Verdict: ready to commit? yes/no
```

---

## P0 — Foundation

```
Write tests for P0 (docs/.../P0-foundation.md) and produce test-reports/P0-report.md.

UNIT/FEATURE (tests/Feature/Tenancy/P0FoundationTest.php):
- migrate then migrate again ⇒ no error (idempotency): assert tables/columns exist once.
- After backfill: every tenant table has 0 rows with NULL subsidiary_id; every user has exactly one
  subsidiary_user row in the first subsidiary.
- Subsidiary::main() resolves config('tenancy.main_slug').
- User::subsidiaries()/belongsToSubsidiary() return the seeded membership.
- migrate:rollback drops subsidiary/subsidiary_user and the column.

E2E: none (no UI change). Assert the existing app pages still load (smoke: GET dashboard 200 for a
seeded user) — proves zero behavior change.

LOAD: none.

Report P0-report.md; do not commit until reviewed.
```

## P1 — Scoping & resolution

```
Write tests for P1 and produce test-reports/P1-report.md.

UNIT (tests/Unit/Tenancy/): BelongsToSubsidiary scope filters by Tenancy::id(); creating() stamps
subsidiary_id; withoutGlobalScope returns all.

FEATURE (the CORE isolation suite, tests/Feature/Tenancy/IsolationTest.php):
- Seed subsidiary A and B with data in each.
- A user scoped to A: Course::all() returns only A's rows; cannot find B's course by id (404/empty);
  creating a course stamps A; attempting to update a B-owned model fails.
- Membership gate: a user who is NOT a member of the current subsidiary is rejected (403) at login
  and on EnsureSubsidiaryMember routes; superadmin is exempt.
- Impersonation sets the impersonated user's subsidiary context.
- Regression: first-subsidiary user retains full access to all existing routes.

E2E (Dusk): login on the (single, main) subdomain still works end-to-end; a non-member hitting a
route sees the 403 page.

LOAD: none (P4 covers concurrency once subdomains are live).

Report; do not commit until the isolation invariant is demonstrably enforced and reviewed.
```

## P2 — Capabilities

```
Write tests for P2 and produce test-reports/P2-report.md.

FEATURE (tests/Feature/Tenancy/CapabilityTest.php):
- hasCapability()/capabilityConfig() return enabled set + merged config (defaults + override).
- capability: middleware ⇒ a subsidiary WITHOUT a capability 404s its routes; WITH it, 200.
- Nav: @capability hides/show links per subsidiary (assert HTML contains/omits the route).
- Attendance reads thresholds from capabilityConfig (strict vs lenient) with course override winning.
- First subsidiary has all capabilities ⇒ all existing routes still 200 (regression).
- Cache: toggling a capability busts sub:{id}:caps and changes routing on the next request.

E2E (Dusk): on a capability-disabled subsidiary the feature nav item is absent and the URL 404s; on
an enabled one it works.

LOAD: none.

Report; do not commit until reviewed.
```

## P3 — Roles & permissions

```
Write tests for P3 and produce test-reports/P3-report.md.

UNIT: permissionsIn() returns the union of granted permissions, cached and busted by
permissions_version; superadmin ⇒ all. RoleTemplateService::cloneInto limits to enabled capabilities.

FEATURE (tests/Feature/Tenancy/PermissionTest.php):
- permission: middleware allows when granted, 403 when not; capability 404 takes precedence.
- DIFFERENT ROLES PER SUBSIDIARY: a user is 'student' in A and 'servant' in B; assert A-context
  checks see student perms only, B-context see servant perms only.
- SUBSIDIARY-SPECIFIC ROLES: create a custom role in B not present in A; grant it a permission;
  assert it works with no code change; assert A has no such role.
- Guardrails (RolePermissionPolicy): admin cannot grant a permission they lack; cannot grant a
  permission whose capability is disabled; cannot grant platform-level perms; cannot remove the last
  admin/role.manage grant (lockout protection).
- Cache invalidation: toggling a grant changes effective access after permissions_version bump.
- Migration of legacy checks: every former role:/hasAnyRole route now enforces the mapped
  permission; first-subsidiary roles backfilled so existing users keep equivalent access (assert a
  representative instructor/admin/student keeps/loses the right routes).

Also: a data-integrity check — assert user.email is globally unique (add the constraint if missing)
since the shared-pool identity depends on it.

E2E (Dusk): the role × permission matrix screen — toggling a permission changes what that role's
user can access; lockout protection prevents removing the admin's role.manage.

LOAD: none.

Report including the full route→permission audit list; do not commit until reviewed.
```

## P4 — Subdomains live (incl. LOAD)

```
Write tests for P4 and produce test-reports/P4-report.md.

FEATURE: IdentifySubsidiary resolves slug + custom domain + console host (Host header variations);
unknown/suspended ⇒ 404; console host ⇒ no tenant binding + superadmin-only.

E2E (Dusk, multi-domain): SSO — login on academy.inst.test ⇒ authenticated on service.inst.test IF a
member, 403 if not; subsidiary switcher lists memberships and navigates; admin.inst.test serves the
console for superadmin only; force-logout/flush-all logs out across subdomains (DB sessions).

LOAD (k6, tests/Load/p4-concurrency.js) — the large-concurrent-users target:
- Scenario A — single subsidiary under load: ramp to N concurrent virtual users (e.g. 500→2000)
  hitting dashboard + a scoped list (attendance/curriculum) on one subdomain. Thresholds:
  p95 < 500ms, error rate < 1%.
- Scenario B — multi-tenant mix: VUs spread across 3+ subdomains concurrently; assert NO cross-tenant
  leakage under load (responses only contain the requesting subdomain's data — tag + assert).
- Scenario C — login/session storm: concurrent logins to validate DB-session driver under load.
- Capture p50/p95/p99, RPS, error rate; run EXPLAIN on the hot scoped queries and confirm the
  subsidiary_id indexes are used (attach to report).
Seed a realistic dataset (e.g. 10k users, 50 subsidiaries, 100k attendance rows) via a seeder for
the load runs.

Report with the load metrics + EXPLAIN evidence; do not commit until thresholds are met and reviewed.
```

## P5 — Provisioning & customization UI

```
Write tests for P5 and produce test-reports/P5-report.md.

FEATURE: TenantProvisioningService::create() is transactional (failure ⇒ full rollback, no partial
subsidiary); creates capabilities + cloned roles + memberships + admin grants + audit entry.
Invitation: existing email ⇒ membership added (NO duplicate user, no unique-email error); new email ⇒
unverified user + invited membership + set-password mail. Self-service (manage.*) bound to current
subsidiary and blocked by RolePermissionPolicy for cross-tenant/escalation attempts.

E2E (Dusk): superadmin completes the Create-Subsidiary wizard ⇒ the new subdomain is reachable and a
linked admin can log in and create content that is isolated; subsidiary admin edits roles/branding
within guardrails; branding renders per subdomain.

LOAD: light — provisioning is low-frequency; smoke-test 10 concurrent provisions don't deadlock.

Report; do not commit until reviewed.
```

## P6 — Pilot (full regression + soak)

```
Produce test-reports/P6-report.md as the GA gate.

- Run the ENTIRE suite (unit+feature+e2e) green against the two-subsidiary pilot dataset.
- Re-run the P4 load scenarios against the pilot (Service tenant: lenient attendance, no exams,
  servant/served roles) and a reporting-only tenant; confirm capability gating + isolation hold
  under load.
- Soak test: sustained moderate load for 30–60 min; assert no memory/connection leak, stable p95.
- Security review (repo /security-review) over tenancy + permission code; attach findings + fixes.
- Final route→permission + capability coverage matrix: every route asserted under at least one
  allowed and one denied case.

Report = the go/no-go for GA; do not commit/tag until reviewed and approved.
```

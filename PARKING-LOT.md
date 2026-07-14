# Parking Lot

Out-of-phase findings. Captured, deliberately NOT built now.

## Environment / config debt (fold into P0 where cheap)
- Server has PHP 8.2, 8.4 AND 8.5 installed. CLI defaulted to 8.4 while FPM runs 8.2.
  Pinned CLI to 8.2 via update-alternatives. Consider removing 8.4/8.5 once stable.
- ext-curl and ext-gd were missing from PHP 8.2 despite being required in composer.json
  (simple-qrcode needs gd; reverb/pusher needs curl). Installed. Implies those features
  were never exercised in production — verify.
- Production sends `Access-Control-Allow-Origin: http://localhost:3000` — dev leftover
  in config/cors.php. Should be env-driven and locked down in production.
- GitHub Actions deploy pipeline is unreliable (SSH i/o timeouts). Manual deploy.sh is
  the current path. Revisit CI/CD deployment after P0.

## Product ideas (see master-plan §12 parking lot for the full list)
- The `user` table has many NOT NULL columns with no defaults (profile_photo, 
  national_id, job, date_of_birth...). This makes programmatic user creation 
  (seeders, tests, admin tools) painful and forces placeholder data. Most of 
  these are profile attributes, not auth attributes — they should be nullable, 
  and in P2 they migrate to `people` anyway.

## Service above Course (department layer)
- Spec: year-agnostic Service owns membership/RBAC/org; Course alone owns
  attendance, grades, exams, lectures, graduation.
- Users have a primary Service; admins may cross-add existing Service users
  into another Service. Course enroll requires Service membership.
- Distinct from multi-subsidiary P6 “Service” tenant type.
- Implemented (expand): schema, membership, Roles Hub Service section + templates,
  service context picker, service roster, service-targeted announcements,
  minimal service applications (single-message form).
- Still deferred: richer form builder, `course.service_id` NOT NULL contraction,
  BelongsToChurch when tenancy lands.
  Plan: `.cursor/plans/service_entity_layer_c1010b64.plan.md` / `service_entity_layer_c8cd74f8.plan.md`

## Church layer above Service — multi-church platform (THE multi-tenant core)
Requested 2026-07-14. This is the "Khedma" multi-tenant migration itself, not a feature.
Exceeds the current phase (tenancy foundation does not exist: no `church_id`,
no `MULTI_TENANT`, no `BelongsToChurch`, no `app/Tenancy/`, master-plan file not yet
authored). Captured here per CLAUDE.md rule 10; must be built via phased expand-contract,
each phase its own PR, behind `MULTI_TENANT=false` until cutover, with the tenant-isolation
"sacred suite" green. Full requirement:

- **Hierarchy:** Church → Services → Courses. Church is a new top entity above Service.
- **Tenant isolation (sacred):** each church is accessible only to its own church admins;
  no cross-church read/write by anyone from another church. Every tenant-scoped model gets
  `church_id` + the `BelongsToChurch` global scope (rules 1–3); `TenantIsolationTest` must
  activate and pass.
- **Church management module** (per church), extensible. First occupants:
  - **Priests** with **confession calendars**, each priest self-configuring availability.
  - **Priests/servants** with **home-visit schedules**.
  - **Financial module**: payroll + money-in to the church. Money = integer minor units +
    currency + fx_rate, never floats (rule 7). Explicitly "extended later".
- **Church registration:** a public church-registration panel submits an application/request
  to the SuperAdmin, who approves it (same pattern as course applications) to provision the
  church tenant.
- **Applications center refactor:** make applications **polymorphic** over Church | Service |
  Course, with the target type chosen at create/edit time (one review center, three subjects).

Sequencing (proposed, not yet approved):
  1. Author master-plan §7 for the Church layer (source of truth; currently missing).
  2. Phase 1 — EXPAND foundation (additive, zero behavior change, `MULTI_TENANT=false`):
     `churches` table; nullable `church_id` on tenant-scoped tables backfilled to church 1
     (AvaPachomius = Tenant Zero); `App\Tenancy\BelongsToChurch` + `TenantContext` +
     `ResolveTenant`; isolation suite goes green.
  3. Church registration + approval (polymorphic applications center).
  4. Church management module shell → priest confession calendars → home-visit schedules.
  5. Financial module (payroll + money-in), money as integer minor units.
  6. CONTRACT (Phase 5-style, dedicated PRs): NOT NULL `church_id`, cutover `MULTI_TENANT=true`.
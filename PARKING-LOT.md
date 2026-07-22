# Parking Lot

Out-of-phase findings. Captured, deliberately NOT built now.

## Environment / config debt (fold into P0 where cheap)
- Server has PHP 8.2, 8.4 AND 8.5 installed. CLI defaulted to 8.4 while FPM runs 8.2.
  Pinned CLI to 8.2 via update-alternatives. Consider removing 8.4/8.5 once stable.
- ext-curl and ext-gd were missing from PHP 8.2 despite being required in composer.json
  (simple-qrcode needs gd; reverb/pusher needs curl). Installed. Implies those features
  were never exercised in production — verify.
- CORS is env-driven via `CORS_ALLOWED_ORIGINS` (see config/cors.php). Keep production
  origins locked down; Expo localhost patterns remain for local mobile dev only.
- GitHub Actions deploy pipeline is unreliable (SSH i/o timeouts). Manual deploy.sh is
  the current path. Revisit CI/CD deployment after P0.

## P0.1 sweep leftovers (2026-07-16)
- Duplicate migration timestamps remain by design (already applied in prod; reorder unsafe):
  `2026_07_18_000001_*` (branding + dynamic RBAC) and `2026_07_19_000001_*`
  (assignments course_id + user created_at backfill). Alphabetical order is fine today.
- Broad Unit/Feature suites still have pre-existing failures; CI `full-suite-report` is
  non-blocking. Closing those gaps is separate from the gated pipelines.
- `DatabaseSeeder` is intentionally empty; `RbacSeeder` / `permissions:sync` are not
  wired into `migrate:fresh --seed` (apps rely on artisan commands / staging data).
- Dormant `App\Http\Controllers\Auth\PasswordResetLinkController` kept (namespace fixed
  for PSR-4); live forgot-password flow uses `ForgotPasswordController`.

## P1.1 organizations registry (2026-07-16)
- `organizations` table (§4 shape) is the canonical tenant registry; product code still
  uses church-native names (`church`, `church_id`, `BelongsToChurch`) during expand.
- Tenant Zero = `organization_id` 1 / `subdomain=avapakhomios`, numerically aligned with
  `church.church_id` 1. FK: tenant `church_id` → `organizations.organization_id`.
- T1+ scopes/middleware already exist on staging but stay dormant while `MULTI_TENANT=false`.
  Do not enable enforcement in production until T7 cutover PR.

## Mobile (React Native)
- Student-first Expo app lives in sibling repo `AvaPachomius-Khoddam-Mobile`.
- Backend slice: Sanctum token auth + `/api/v1` read APIs — see `docs/mobile/mvp.md`.
- Design tokens: `resources/design-tokens/khoddam.tokens.json` (sync to mobile theme).
- Deferred: push device tokens, write APIs, staff app, store release pipeline.

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

## T7 deferred / ops follow-ups
Landed on `feature/church-tenancy-t7`: `church_id` backfill + MySQL NOT NULL contract,
BelongsToChurch dormant stamp, `tenancy:seed-pilot-church`, cutover runbook.
Still parked / ops-owned:
- Flip production `MULTI_TENANT=true` (staging first; see `docs/tenancy-cutover.md`)
- Wildcard DNS/TLS + SESSION_DOMAIN for shared SSO cookies
- Full P6 checklist sign-off (`docs/architecture/multi-subsidiary/P6-pilot.md`)
- Optional FK hardening on every tenant table → `organizations.organization_id`
- Public church registration / polymorphic applications (§13)

## T6 deferred (finance first-cut boundary)
Landed on `feature/church-tenancy-t6`: payroll runs/lines + money-in with integer
minor units, currency, fx_rate; church-admin finance permissions; draft→finalize.
Still parked (master-plan §11 / §17.5):
- Multi-currency catalogs beyond default EGP / fx_rate=`1`
- Payroll cadence automation (monthly generators)
- Approval workflows / multi-step sign-off before finalize
- Reporting, reconciliation, exports
- Per-church base-currency in `church.settings`

## T4 deferred (inside T4 / awaiting product decisions)
Landed on `feature/church-tenancy-t4`: TrustHosts, sessions migration, `ChurchHost`,
`ChurchProvisioningService`, superadmin churches CRUD, nav church switcher (host
links), login membership rejection, `EnsureChurchMember` on web stack.
Still parked:
- Public church-registration panel → superadmin approval (master-plan §13 / §17.4).
- Polymorphic applications center (Church | Service | Course).
- Church-admin self-service screens on `{slug}` (members/branding within guardrails) —
  superadmin console covers provisioning for now.
- Invite-by-email onboarding that creates unverified users (add-member requires
  existing email today).
- Per-church branding resolution wired into ThemeController / locale defaults.
- Wildcard DNS/TLS + deploy docs updates (infra; document in DEPLOY when staging
  enables MULTI_TENANT).

## T8 residual — post T8b (parked)

**Landed as T8a + T8b:** structure templates/anchors/`service_units`/`servants-prep`; slug
binding + `/s/{service}` hub + numeric 301s; `enrollments` dual-write (UCR still SOT for reads);
attendance `lock_version` CAS; nav filtered by structure anchors.

**Still parked:**

1. Contract: drop/rename `user_course_role` only after enrollments cutover sign-off (Phase 5 style).
2. Broader `/{service:slug}/…` route tree beyond hub + existing `/services/{slug}/…` (full product wrap).
3. Nav registry driven *purely* from structure template (today: incremental anchor tags only).

**Resume when:** T8b merged to staging and smoke-checked; contract items wait for a dedicated cutover PR.

## Structure template engine + wrap as service #1 (superseded 2026-07-22)

Original full request parked 2026-07-16; **T8a/T8b delivered the expand track**. Residual items
live under **T8 residual** above. Do not re-open the old block for new work.

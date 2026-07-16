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

## T4 — Church switcher + provisioning UX (requested 2026-07-16)
Parked until **T3 (roles & permissions)** lands. T3 expand WIP exists on
`feature/church-tenancy-t3` (RBAC `church_id` + `permissions_version` only; enforce
not done). Master-plan T4 = P4/P5 (subdomains + provisioning + church registration /
polymorphic applications). Sketch (mirror Service/Course switchers, host-based):

### Switch churches (nav — NOT session like Service/Course)
- Placement: left of the Service switcher in `navigation.blade.php`.
- Show when `MULTI_TENANT=true` and user has ≥2 `church_user` memberships (or
  superadmin with ≥1 church).
- Each item is an **`<a href="https://{slug}.{base}/...">`** (or custom `domain`),
  not a POST that sets session — church is resolved by host (`ResolveTenant`).
- Current church: label + icon from `currentChurch` (already view-shared).
- Single membership: label only (same pattern as `showServiceContextLabel`).
- Login rejection (non-member on host): message linking to their other churches’
  subdomains (“switch church”).
- SSO: `SESSION_DOMAIN=.{base}` + DB sessions (P4); membership gate still 403s.

### Manage churches (superadmin console — `TENANCY_CONSOLE_HOST`)
- Console host unbound (no `TenantContext`) — cross-church visibility.
- Screens: Churches list/create/edit/suspend; per-church capabilities; members
  invite/add/remove; branding in `church.settings`.
- Create → `ChurchProvisioningService`: church row + default capabilities +
  `church_user` for admin(s) + (after T3) clone role templates + audit_log.
- Church-admin self-service stays on `{slug}.{base}` (scoped); cannot enable new
  capabilities (superadmin only).
- Deferred inside T4 until product decisions: polymorphic applications center
  (§13), church-registration public panel → approval provisioning (open decision
  §17.4: auto-provision vs finish-setup).

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

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
- **On-device register / mobile OTP:** design locked under **Contact verification (mobile-first)** below;
  Wave E in `docs/mobile/student-feature-matrix.md` depends on that epic (not “can stay web”).

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
- Full P6 checklist sign-off (`docs/architecture/multi-subsidiary/P6-pilot.md`) — use `docs/staging-acceptance-checklist.md` + `php artisan tenancy:acceptance-check`
- Optional FK hardening on every tenant table → `organizations.organization_id`
- ~~Public church registration panel (§13)~~ — landed; ~~polymorphic applications hub UI~~ landed (no storage merge)

## T6 deferred (finance first-cut boundary)
Landed on `feature/church-tenancy-t6`: payroll runs/lines + money-in with integer
minor units, currency, fx_rate; church-admin finance permissions; draft→finalize.
Still parked (master-plan §11 / §17.5) — **all four approved to build 2026-07-30**, no
further product decision needed, sequencing TBD:
- Multi-currency catalogs beyond default EGP / fx_rate=`1`
- Payroll cadence automation (monthly generators)
- Approval workflows / multi-step sign-off before finalize
- Reporting, reconciliation, exports
- Per-church base-currency in `church.settings`

## T4 deferred (inside T4 / awaiting product decisions)
Landed on `feature/church-tenancy-t4`: TrustHosts, sessions migration, `ChurchHost`,
`ChurchProvisioningService`, superadmin churches CRUD, nav church switcher (host
links), login membership rejection, `EnsureChurchMember` on web stack.
Still parked (product decisions recorded 2026-07-30 — see master-plan §17.4):
- ~~Public church-registration panel → superadmin approval (master-plan §13 / §17.4)~~ —
  landed: public `/register-church` lead form + `church_applications` queue; approve only
  marks the row and hands off to existing `superadmin.churches.create` (no auto-provision).
- ~~Polymorphic applications center (Church | Service | Course)~~ — landed as shared
  review **hub UI** (`admin.applications-hub`) over existing per-type tables (no storage
  merge). RegistrationApplication (person onboarding) excluded from v1; approve/reject
  still delegate to each type’s existing show/service. Storage merge stays Phase-5-only.
  Auth-scope hardened: service rows filtered by tenant/`canInService`; church rows gated
  by `platform.church_applications` (not raw role-name checks).
- ~~Church-admin self-service screens on `{slug}` (members/branding within guardrails)~~ —
  branding landed under public_site (T10b); **members self-service** at `/church/members`
  (`church.members.manage`) with add-existing or invite-by-email/WhatsApp.
- ~~Invite-by-email onboarding~~ → delivered under **People Onboarding** epic
  (`person_placements`, `invitations`, CSV import + bulk invite email/WhatsApp).
  Church `addMember` (superadmin + `/church/members`) invites unknown emails via
  `ChurchMemberInviteService` (OTP claim); existing emails stay add-member.
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

**Resume when:** dedicated cutover / product-wrap PRs after T9 expand track; contract items wait.

## Structure template engine + wrap as service #1 (superseded 2026-07-22)

Original full request parked 2026-07-16; **T8a/T8b delivered the expand track**. Residual items
live under **T8 residual** above. Do not re-open the old block for new work.

## Service cycle progression — T9 residual (parked 2026-07-25)

**Requested / locked design:** End-of-Cycle wizard (propose → admin confirm; never silent
auto-promote); progression policy on structure template + service create override
(`school_year_ladder` / `semester_cohort` / `continuous_open` / `course_close_only`);
per-service progression only; people-only roster rows; church school year as season +
Church Cycle Dashboard (no one-button church-wide upgrade). Plan:
`.cursor/plans/service_year_progression_5fc2e925.plan.md`. Feature-gap **F-19**.

**T9a (landed):** policy defaults on templates; service override columns; roster/enrollment
status fields; resolver + create/edit UX; eligibility (inactive/hold excluded from propose).

**T9b (landed / stacked):** End-of-Cycle wizard (propose → confirm → apply); ladder edges in
`progression_config`; UCR dual-write promote; audit + admin notify; people-only still skipped.

**T9c (landed):** `church_school_year` season + Church Cycle Dashboard + Start promotion season
(no global blind upgrade).

**Still parked for later PRs:**

1. ~~People-only placement table~~ → delivered as `person_placements` in People Onboarding.
   End-of-Cycle wizard still skips people-only rows until a follow-up wires placements into propose/apply.
2. Optional staff-only reassign step beyond shared enrollment promote.

**Resume when:** wire people-only placements into End-of-Cycle propose/apply; staff residual as needed.
## Public Church Presence / Homepage CMS (T10a–T10c landed; T10d parked)

**Requested:** After portal church config + public church details, a permission-gated
**curated-section homepage editor** (colors, fonts, themes, sections, images,
responsiveness) so each church can publish a dynamic public homepage on its host /
custom domain. Not a freeform page builder.

**Design (locked):** [`docs/public-church-cms.md`](docs/public-church-cms.md) —
homepage-first curated sections; capability `public_site` + keys
`public_site.profile|theme|manage|publish`; draft/publish `church_site` /
`church_site_section` / `church_media`; BYO-domain DNS cutover (Mode A); enterprise
notes for dedicated DB (Tier 4 now / Tier 3 later) and white-label mobile (M2).
Plan: `.cursor/plans/church_homepage_cms_4247561e.plan.md`.

**Status:** **T10a + T10b + T10c landed** on staging. Sign-off:
`docs/staging-acceptance-checklist.md` Part C + `php artisan tenancy:acceptance-check --t10c`.
**T10d multi-page** remains parked.

**Resume when:** T10c signed off. Next: T10d multi-page (optional) or ops polish.
Feature-gap **F-20** (delivered for homepage v1).

## Contact verification (mobile-first) (parked 2026-07-25)

**Requested:** Mobile number verification (SMS/WhatsApp) for future communications; whether
to dual-verify email+mobile; WhatsApp/Telegram preference; native-app registration path.

**Design (locked):** plan `.cursor/plans/mobile_verification_design_bd654111.plan.md`

- **One signup OTP per client:** web → email OTP; native app → mobile OTP (WhatsApp primary,
  SMS fallback later). Never both mandatory in one funnel.
- **Identity:** person = unique `national_id`; account = `user_id`; `email` / `mobile_number`
  remain unique auth channels (no shared OTP targets for mother/sister). Family contact via
  people/family guardian — not a second login on the same channel.
- **App user without verified email:** account fully usable (portal + push + WhatsApp);
  block email notifications + email password-reset until email proven; **mobile OTP password
  reset** is in-scope for this epic.
- **Gates:** automated WhatsApp/SMS only if `mobile_verified_at`; email channel only if
  `email_verified_at` (web signup sets this; app proves later). Ask `whatsapp_capable`; no Telegram v1.
- Expand-only schema when built: `mobile_verified_at`, `email_verified_at`, `whatsapp_capable`,
  channel-aware OTP storage (today’s `otp_code` PK=`user_id` is insufficient).
  **Note (People Onboarding):** the three stamp columns landed additively on `user`; full
  channel-aware OTP + WA OTP verify (CV1) remains parked.

**Why parked:** Master-plan §7 current phase is **T8**. Not a tenancy table slot; product/auth
epic that must not start mid-T8. CLAUDE.md rule 10 → park; **no** migrations, OTP channel
code, or API register while T8 is in progress.

**Also waiting / related:**
- Meta WhatsApp **authentication/OTP templates** (ops + Business Manager) before production WA OTP
- SMS provider (Twilio/local) — only after WA primary path works
- Push device tokens (Mobile section) — daily in-app channel; pair with CV4 below
- Optional later (separate park): passwordless login-with-mobile-OTP; Telegram

### Implementation order (resume sequence)

Prerequisite: **T8 residual smoke-checked** (same gate as other post-T8 product work).

This epic is **independent of T10** (Public Church Presence). Prefer it **before or in parallel
with early T10** if mobile-first daily use is the higher product priority; do **not** block T10
on CV, and do **not** start Expo register (Wave E) before CV1–CV3 backend.

| Step | Slice | Delivers | Depends on |
|------|--------|----------|------------|
| **CV1** | Backend expand | `mobile_verified_at` / `email_verified_at` / `whatsapp_capable`; channel-aware OTP; WhatsApp OTP send+verify; web progressive mobile verify from notification settings; dispatch + prefs gates; audit; tests | T8 smoke; WA API + OTP template configured on staging |
| **CV2** | Recovery | Password reset via mobile OTP when email unproven; email link when email proven | CV1 |
| **CV3** | API | `/api/v1` register + channel-aware OTP verify/resend (app signup gate = mobile OTP; sets `mobile_verified_at`) | CV1 |
| **CV4** | Native app (Wave E) | Expo register/OTP screens + autofill; soft email-confirm nag; push token registration for daily use | CV2 + CV3; Mobile MVP auth stable |

After CV4: notification preference completeness (feature-gap **F-10**) can fold verified-channel
rules into digests; optional SMS fallback provider.

**Resume when:** T8 residual smoke-checked — **confirmed by product owner 2026-07-30**, this
epic is unblocked. Note: the *narrow* CV1 slice (channel-aware `mobile_verified_at` stamp,
WhatsApp OTP send/verify, web progressive verify from notification settings) already shipped
— see `audit_status.md` Category A. What remains of CV1 (registration-time channel-aware OTP,
broader dispatch/prefs gating beyond the existing `mobile_verified_at` check) plus CV2/CV3
can proceed. Still genuinely blocked on ops: CV1's own prerequisite row above requires
"WA API + OTP template configured on staging" (Meta Business Manager approval) — verify that
before building the registration-time OTP send path.

## Priest appointment calendar (Calendly-like) (parked 2026-07-26)

**Requested:** Full calendar system on top of confessions + a separate pastoral-appointment
calendar — priest/secretary open/block slots; approved church members book; edit / cancel /
reschedule; configurable portal/email/WhatsApp notifications; ICS then Google/Outlook OAuth;
secretary book-on-behalf and outreach for reminders/rescheduling.

**Design (locked):** [`docs/priest-appointment-calendar.md`](docs/priest-appointment-calendar.md) —
plan `.cursor/plans/priest_calendar_booking_2563fdff.plan.md`.

- **Two calendars:** keep `confession_slot` / `confession_booking`; add parallel
  `appointment_type` / `appointment_slot` / `appointment_booking` (shared PHP booking engine).
- **Secretary:** new `secretary` role template **and** `priest_secretary` delegation; secretary
  sees booker identity + notes; may book on behalf (`booked_by_user_id`).
- **Who books:** any approved church member (`*.book`); not limited to role-name “servant”.
- **Notifications:** lifecycle + reminder types; portal/email in PAC4; WhatsApp only after
  Contact Verification CV1 gates.
- **Integrations:** tokenized ICS in PAC5; OAuth Google/Outlook push in PAC6 (no two-way busy sync).

**Why parked:** Master-plan §7 current phase is **T8**. Expands beyond shipped T5 confession
CRUD. Does **not** claim T9 or T10. CLAUDE.md rule 10 → park; **no** migrations, permission
catalog entries, routes, or UI while T8 is in progress.

**Also waiting / related:**
- Contact Verification (**CV1+**) before WhatsApp booking messages
- Church timezone on `church.settings` (master-plan §17) — fold into PAC1 settings
- Ops OAuth apps (Google + Microsoft) before PAC6

### Implementation order (resume sequence)

Prerequisite: **T8 residual smoke-checked**. Independent of T10. Prefer CV1 before enabling
WhatsApp for these notification types (portal/email can ship earlier in PAC4).

| Step | Slice | Delivers | Depends on |
|------|--------|----------|------------|
| **PAC0** | Docs | Design doc + this parking entry + feature-gap **F-21** + §9 cross-link | Done with this park |
| **PAC1** | Backend expand | `priest_secretary`; additive booking columns; appointment tables; permission keys + `secretary` template; policies; isolation tests | **Landed** |
| **PAC2** | Confession UX | Grid, open/block, recurrence generate, cancel/reschedule, book-on-behalf, my-bookings | **Landed** |
| **PAC3** | Pastoral calendar | Appointment types + same UX | **Landed** |
| **PAC4** | Notifications | Lifecycle + reminders (portal/email); WA gated on CV1 | **Landed** (WA still blocked until `mobile_verified_at` / CV1) |
| **PAC5** | ICS | Tokenized priest/member feeds | PAC2 |
| **PAC6** | OAuth | Google/Outlook push | PAC5; ops OAuth apps |

**Resume when:** T8 residual smoke-checked — **confirmed by product owner 2026-07-30**.
PAC1–PAC4 already landed. **PAC5 (tokenized ICS)** is unblocked to proceed — no external
dependency. **PAC6 (OAuth)** is still blocked separately on its own ops prerequisite: Google
+ Microsoft OAuth app registration must exist before that code is useful.

## Student module assessment + private instructor notes (SMA)

**Design:** [`docs/product/use-cases/student-module-assessment.md`](docs/product/use-cases/student-module-assessment.md)

| Step | Slice | Status |
|------|--------|--------|
| **SMA0** | Docs | Landed |
| **SMA1** | Schema + RBAC | Landed |
| **SMA2** | Assess UX | Landed |
| **SMA3** | Notes (anonymous UI) | Landed |
| **SMA4** | Church criteria editor + multi-assessor averages | Still parked |

## Platform observability — ops + usage + infra (in progress 2026-07-28)

**Requested:** Full logging/reporting for system errors, crashes, DB errors, login
failures, frontend errors (with affected users), active users per time slot,
server load, and church/service usage windows — portable across cloud providers.

**Design (locked):** plan `.cursor/plans/observability_architecture_7057ba8f.plan.md`

- **Own first-party core** (structured events + rollups + infra samples). Do **not**
  make Hostinger APIs or raw nginx/log scraping the source of truth.
- **Adapters:** `ErrorSink` (null/log/sentry) + `InfraMetricsAdapter` (null/local_proc;
  vendor adapters optional later). Config via `OBSERVABILITY_*` / optional `SENTRY_DSN`.
- **Dual UI:** platform master `/superadmin/observability` (console host) + church
  `/admin/observability` (tenant-scoped; no Load/infra). Permissions
  `platform.observability.*` / `church.observability.*`.
- **Waves:** W0 contracts → W1 events/UI → W2 beacon → W3 usage → W4 infra →
  W5 church portal → W6 sinks/alerts/retention.

**Status:** **Landed** on staging (PR #91). Follow-ups: optional Hostinger infra adapter;
church-admin stack sanitization polish; `login_trials` plaintext password debt (separate).

## Team projects — v1 landed, follow-ups parked (2026-08-22)

**Landed:** module-linked project assessments, pack-fill random assignment, min/max team size,
first-member / teammate / team-complete notifications, one approved team-change request.
Design: `docs/product/use-cases/projects.md`.

**Still parked:**

1. Project grading / gradebook integration (points, rubric, announce).
2. File deliverable uploads per phase.
3. Mobile `/api/v1` read/join endpoints.
4. Admin manual seat assignment / lock a team before max.
5. “Someone left” notification to remaining teammates.

## Security / framework upgrade (2026-07-22)
- Laravel 10.50.2 has no official backport for CVE-2026-48019 (email CRLF) or
  GHSA-crmm-hgp2-wgrp (temporary signed URL path confusion). Patches require
  Laravel **12.60+ / 12.61.1+** (or 13.x). App mitigates email CRLF via
  `App\Validation\SafeValidator`. Temporary filesystem signed URLs are unused;
  route signatures (`hasValidSignature`) remain.
- **Do not** start a Laravel 10→12 major upgrade mid-tenancy migration. Schedule a
  dedicated upgrade PR after T7 cutover stability (or when Laravel 10 is EOL and
  blocking).

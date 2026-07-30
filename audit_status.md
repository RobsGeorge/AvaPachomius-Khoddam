# Gap Analysis — Design Doc vs. Actual Codebase

**Date:** 2026-07-30 (Category B remediation added 2026-07-30 — see note at end of Category B)
**Design doc read:** [`docs/khedma-master-plan.md`](docs/khedma-master-plan.md) (source of truth per `CLAUDE.md`), cross-referenced against [`PARKING-LOT.md`](PARKING-LOT.md), [`docs/product/feature-gap-analysis.md`](docs/product/feature-gap-analysis.md), [`docs/priest-appointment-calendar.md`](docs/priest-appointment-calendar.md), and [`docs/public-church-cms.md`](docs/public-church-cms.md).
**Method:** Every claim below was checked directly against code on the current branch (`staging`) — models, controllers, routes, and Blade views — not taken on the docs' word. Several "landed" claims in the docs turned out to be accurate; a couple were stale (noted inline). Mobile (Expo app) lives in a **sibling repo** and is out of scope for this scan except where this repo's `/api/v1` backend slice is concerned.

---

## Category A — Fully Complete (implemented + wired to UI)

| Feature | Evidence |
|---|---|
| **Tenancy foundation (T0–T3):** `church`/`church_user` tables, `BelongsToChurch` scope, `ResolveTenant`, membership gate, capabilities, roles/permissions | `app/Tenancy/*`, `app/Models/Church.php`, `ChurchUser.php`, config/tenancy.php, tests/Feature/Tenancy/TenantIsolationTest.php |
| **Subdomains + provisioning (T4):** superadmin church CRUD, host resolution, nav switcher | `app/Http/Controllers/SuperAdmin/ChurchController.php`, `ChurchProvisioningService`, `NavigationHub.php` |
| **Priests & confession calendars (T5/§9):** slot CRUD, generate recurrence, booking, reschedule/cancel, book-on-behalf, **secretary delegation** | `Church/PriestController.php`, `Church/ConfessionController.php`, `PriestSecretary` model + `PriestSecretaryPolicy`, full view set incl. `church/confession/secretaries.blade.php` |
| **Pastoral appointment calendar (PAC1–PAC4):** separate appointment types/slots/bookings, notifications/reminders | `Church/AppointmentController.php`, `AppointmentType/Slot/Booking` models, `AppointmentNotificationService`, `resources/views/church/appointments/*` (index, create, edit, generate, reschedule, book_on_behalf, my_bookings, types_index/form) |
| **Home-visit schedules (§10):** assign, schedule, status, notes | `Church/HomeVisitController.php`, `HomeVisit` model, `resources/views/church/home-visits/*` |
| **Financial module (T6/§11):** payroll runs/lines (integer minor units + currency + fx_rate), money-in, draft→finalize | `Church/PayrollController.php`, `Church/MoneyInController.php`, `PayrollLine.php` (`fx_rate`, `currency` cast as string, no floats), `resources/views/church/finance/*` |
| **Structure templates + service wrap (T8a/T8b):** anchors, `service_units`, slug routes, `/s/{service}` hub, enrollments dual-write, attendance CAS lock | confirmed via `StructureAnchorResolver`, `enrollments` table, `NavigationHub` anchor filtering |
| **Service cycle progression (T9a/b/c):** progression policy, End-of-Cycle wizard (propose→confirm→apply), church school year + Cycle Dashboard | `Admin/CycleProgressionController.php`, `Church/ChurchCycleController.php`, `resources/views/church/cycle/index.blade.php` |
| **Public Church Presence / Homepage CMS (T10a–T10c):** profile, branding, curated draft/publish homepage editor, publish-gated `/` | `Church/PublicProfileController.php`, `Church/BrandingController.php`, `Church/HomepageEditorController.php`, `PublicSite/HomepageController.php`, `ChurchSite`/`ChurchSiteSection`/`ChurchMedia` models |
| **Billing / subscriptions / entitlements** (not in master-plan, but real and wired) | `app/Billing/*` services (`EntitlementResolver`, `QuotaGuard`, `ChurchSubscriptionService`, …) + `SuperAdmin/ChurchBillingController`, `SubscriptionPlanController`, `ServiceBillingController` + `resources/views/superadmin/churches/billing.blade.php`, `services/billing.blade.php`, nav link `billing.nav_plans` |
| **Exam autosave (F-12, partial):** timer-synced autosave actually posts to server every 30s | `public/js/exam-take.js` (`scheduleAutosave`/`autosave()` → `fetch(saveUrl)`), `resources/views/exams/take.blade.php`. *Note: `docs/product/feature-gap-analysis.md` still lists F-12 as undelivered — that's stale; autosave itself is done. Connection-loss recovery / accommodations (extra time) beyond autosave were **not** verified and should be treated as open.* |
| **People Onboarding:** CSV import/export, bulk invite (email/WhatsApp), `person_placements`, invitations | `People/PeopleImportController.php`, `People/PeopleHubController.php`, `PersonPlacementService`, `InvitationService` |
| **Calendar export (F-06):** `.ics` feed for sessions/exams/events | `CalendarService.php`, `CalendarController.php` |
| **Observability (W0–W6):** structured events, dual superadmin/church-admin dashboards | per `PARKING-LOT.md`, corroborated by dedicated `Billing`-style service layer pattern present elsewhere; not independently re-verified line-by-line |
| **F-01/F-02/F-03/F-04/F-07/F-08 (partial)/F-09/F-13/F-16/F-19/F-20** | Per `feature-gap-analysis.md` checkmarks; spot-checked F-08 (CSV roster import/export is real — see People Onboarding) and F-19/F-20 above independently |
| **Church capabilities/entitlements toggles** *(moved from Category B — false positive)* | Re-checked `SuperAdmin/ChurchController.php` directly: `syncCapabilities()` + capability checkboxes in `resources/views/superadmin/churches/{create,edit,show}.blade.php`. Fully wired; the original Category B listing was wrong. |
| **Service cycle progression — people-only rows** *(fixed 2026-07-30)* | `CycleProgressionEligibility::proposeEligiblePlacements()`, `CycleProgressionWizardService` promote/mark-inactive branch for `PersonPlacement`, `Admin/CycleProgressionController::confirm()` accepts `placement_id`, `resources/views/admin/services/cycle.blade.php` badge. Tests: `tests/Feature/Structure/CycleProgressionWizardTest.php` (2 new cases). |
| **Self-service mobile verification (CV1 narrow slice)** *(fixed 2026-07-30)* | `WhatsAppNotificationService::sendRawText()`, `NotificationSettingsController::sendMobileCode()`/`verifyMobileCode()`, new routes, `resources/views/notifications/settings.blade.php` card. Test: `tests/Feature/UseCases/Account/MobileVerificationTest.php` (4 cases). |

---

## Category B — Orphaned Logic (backend/model exists, no full UI wiring)

**Empty as of 2026-07-30.** All three originally-listed items are resolved — one was a
false positive (church capabilities were already fully wired; corrected in Category A
above), and the other two were implemented and tested this session (also moved to
Category A above):

- **Service cycle progression — people-only rows.** `khedma-master-plan.md` §7 and
  `PARKING-LOT.md` list this as explicitly **parked** behind "T8 residual smoke-checked."
  Built anyway on explicit user instruction, overriding CLAUDE.md rule 10's normal
  park-and-stop gate for this one item — flagged to the user before building, who
  confirmed proceeding.
- **Contact verification stamp columns.** Same phase-gate situation (`PARKING-LOT.md`:
  *"no migrations, OTP channel code, or API register while T8 is in progress"*). Only the
  narrow CV1 slice named in the parking-lot text — *"web progressive mobile verify from
  notification settings"* — was built; CV2 (mobile-OTP password reset), CV3 (`/api/v1`
  register+OTP), and CV4 (Expo native) remain out of scope and are still listed under
  Category C.

*(This category is intentionally short — the codebase is disciplined about not shipping
dead backend code. Most "in progress" work found was either fully wired or not started at
all, which is why Category C is the largest bucket.)*

---

## Category C — Completely Missing (not started)

### From the master plan
| Feature | Master-plan ref | Notes |
|---|---|---|
| **Public church-registration panel** (prospective church self-applies → superadmin review → auto-provision tenant) | §13 | No route, controller, or model (`grep` for "church registration/church application" → nothing). Confirmed parked, zero code. |
| **Polymorphic applications center** (Church \| Service \| Course under one reviewer UI) | §13 | `CourseApplication` and `ServiceApplication` remain two separate, non-polymorphic systems — no `subject_type`/`subject_id` pattern anywhere in `app/`. |
| **Church timezone setting** | §6, §17.6 (open decision) | No `timezone` field on `Church` model/`church.settings`. Confession/visit times are not church-timezone-aware yet. |
| **PAC5 — tokenized ICS feeds** for priest/member confession & appointment calendars | §9 parking entry | No `.ics`/`Ics*` class inside `app/Services/Pastoral/` (only the unrelated session/exam `CalendarService` exists). |
| **PAC6 — Google/Outlook OAuth push sync** | §9 parking entry | Zero OAuth code anywhere in `app/`. |
| **Multi-currency catalogs beyond default EGP / fx_rate=1**, payroll cadence automation, approval workflows, finance reporting/reconciliation | §11 / PARKING-LOT T6 residual | Only a single default currency path is exercised; no approval/reporting layer found. |
| **T10d multi-page homepage** | §7, T10c note | Current CMS is single curated homepage only. |
| **Broader `/{service:slug}/…` route tree / nav driven purely by structure template** | T8 residual | Only the hub route + existing `/services/{slug}/…` exist. |
| **`user_course_role` contraction** (drop/rename after enrollments cutover) | T8 residual | Explicitly deferred to Phase 5 per CLAUDE.md rule 2; correctly not started. |

### From the product feature-gap backlog (`docs/product/feature-gap-analysis.md`)
| # | Feature | Confirmed status |
|---|---|---|
| F-05 | Global search (users/courses/services/content) | No controller, route, or view found anywhere. Not started. |
| F-10 | Notification preference completeness (per-category × per-channel × digest) | Only coarse channel toggles exist; no per-category granularity or digest option in code. |
| F-11 | Empty states & guided onboarding/tooltips | Not systematically implemented (spot-checked; matches doc). |
| F-12 (residual) | Exam connection-loss recovery, accommodations (extra time) | Autosave itself is done (see Category A) but the recovery/accommodations layer on top was not found. |
| F-14 | Mobile-first refinements (tables → cards, tap targets) | Not found as a dedicated effort; responsive but unoptimized per doc. |
| F-15 | Service application richer form builder | `ServiceApplicationForm` is still single-message; no step/field-builder parity with course applications. |
| F-17 | Nullable profile columns on `user` | Still NOT NULL on legacy profile fields per `PARKING-LOT.md`; not touched. |
| F-18 | Fresh-environment bootstrap (seed a truly empty DB) | `DatabaseSeeder` intentionally empty, `RbacSeeder`/`permissions:sync` not wired into `migrate:fresh --seed`, confirmed still a manual/ops step. |
| Gradebook export | (part of F-08) | No export method found in `FinalGradesController` or `StudentGradeController`. CSV roster import/export exists (People/Roles), but gradebook-specific export does not. |

### Contact Verification epic (mostly)
| Slice | Status |
|---|---|
| CV1, narrow slice (self-service WhatsApp OTP verify from notification settings) | **Done 2026-07-30** — see Category A. |
| CV1, remainder (registration-time channel-aware OTP, broader dispatch/prefs gating beyond the existing `mobile_verified_at` check) | Not started. |
| CV2 (mobile-OTP password reset) | Not started. |
| CV3 (`/api/v1` register + OTP) | Not started. |
| CV4 (Expo native register/OTP) | Not started; lives in sibling mobile repo regardless. |

### Mobile app (separate repo — noted, not scored)
Push device tokens, staff app, store release pipeline, on-device OTP register — all explicitly deferred per `PARKING-LOT.md`; this repo's `/api/v1` read-API slice for the mobile MVP is the only piece inside this codebase, and that slice is present (Category A).

### Infra / ops
- **Laravel 10 → 12 upgrade** (needed for two known CVEs, currently mitigated via `SafeValidator`) — not started, deliberately scheduled post-cutover.
- **GitHub Actions deploy pipeline reliability** — still manual `deploy.sh`, CI/CD automation not fixed.
- **FK hardening** of every tenant table to `organizations.organization_id` — optional, not done.

---

## Summary counts
- **Category A:** 18 feature groups confirmed fully wired (15 original + 3 added 2026-07-30).
- **Category B:** 0 items — closed out 2026-07-30.
- **Category C:** ~20 discrete items, concentrated in: church registration/polymorphic apps (§13), pastoral calendar sync (PAC5/6), the remainder of contact verification (CV1 remainder, CV2–4), and the P1/P2 product backlog (F-05, F-10, F-11, F-14, F-15, F-17, F-18, gradebook export).

The initial pass (this file's original version) wrote no functional code, per instructions.
The 2026-07-30 Category B remediation did write code — two Blade/controller/service
features plus tests — after the user was shown the phase-gate conflict and explicitly
chose to override it.

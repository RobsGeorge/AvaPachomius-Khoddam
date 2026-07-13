# Automated Test Suite Plan — Khedma / AvaPachomius-Khoddam

**Status:** Living document. **Owner:** engineering. **Last updated:** 2026-07-13.

This is the plan for the **automated** test suite (PHPUnit) across the whole platform.
Manual QA/UAT is out of scope here. Where a section says "future", it describes
scaffolding to add as the Khedma multi-tenant migration reaches that phase — do not
build ahead of the current master-plan phase (CLAUDE.md rule 10).

---

## 1. Current state (baseline)

- **Runner:** PHPUnit via `phpunit.xml`. Three suites: `Unit`, `Feature`, `Load`.
- **DB under test:** SQLite `:memory:`, rebuilt per test via `RefreshDatabase`.
- **Env:** `APP_ENV=testing`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`,
  `CACHE/SESSION=array`, `BCRYPT_ROUNDS=4`.
- **Shared base:** `tests/Support/EventModuleTestCase.php` seeds `RbacSeeder` in
  `setUp()` and exposes builder helpers (`createUser`, `createCourse`,
  `courseRoleWithPermissions`, `assignCourseRole`, `createEvent`, `makeEventAdmin`,
  `seedBasicRoles`). Most feature tests extend this, not `Tests\TestCase` directly.
- **Factories:** only `UserFactory` exists today. Model creation is done through the
  hand-rolled helpers above.
- **Permissions:** canonical keys live in `config/permissions.php` and are loaded via
  `php artisan permissions:sync` (run inside `RbacSeeder`). Tests reference keys by
  string (e.g. `academic.exams.manage`, `role.manage`).

### 1.1 What is already covered (~35 files)

| Area | Files |
|---|---|
| Auth | `Auth/{Authentication,EmailVerification,PasswordReset,Registration}Test`, `LoginPageTest` |
| Events module | `Events/*` (admin flow, check-in, reservation, dashboard), `Unit/Events/*`, `tests/Load/Events/*` |
| Roles / RBAC | `RolesHubTest`, `DynamicRoleManagementTest`, `UserCourseRoleIndexTest`, `RoleAssignmentNotificationTest` |
| Notifications | `NotificationHubTest`, `NotificationActionLinkTest`, `SessionUpcomingNotificationTest`, `Unit/SessionNotificationServiceTest` |
| Course lifecycle | `CourseApplicationReviewTest`, `CourseContextTest`, `AssignmentCourseContextTest`, `CourseGraduation*Test`, `RegistrationApplicationReviewTest`, `StudentOnboardingTest` |
| Misc | `AnnouncementModuleTest`, `AttendanceRosterTest`, `ProfilePhotoAdminTest`, `StudentBirthdayAnnouncementTest`, `FeedbackSurveyRouteTest`, `LiveQuizHostEndTest` |

### 1.2 Coverage gaps (no automated tests today)

Priority modules with **zero** tests: **Exams** (online exams, questions, attempts,
grading, proctoring, timer, schedules), **Grades** (grade categories/items, student
grades, final grades, exam grades), **Certificates**, **Curriculum**
(module/lecture/lecture-material/content lifecycle), **Assessments**, **Impersonation**
& **ForceLogout**, **WhatsApp notifications**, **OTP**, **Locale/Theme**,
**SuperAdmin audit**, **CoursePermissionResolver**, **CourseEnrollment**, and most of
the ~60 service classes at the unit level.

---

## 2. Goals & non-goals

**Goals**
1. A permission/authorization regression net so RBAC changes (rule 4: policies +
   permission keys only) can't silently open or close access.
2. Fast, deterministic feature coverage of every user-facing flow's happy path plus its
   primary authorization and validation failure paths.
3. Unit coverage of the service layer where logic is non-trivial (money, scoring,
   eligibility, scheduling, notification fan-out).
4. A **tenant isolation** harness ready to become the "sacred suite" the moment
   `BelongsToChurch` lands (rule 3, 9).
5. Notification & email assertions (portal notification row + `Mail::fake`) on every
   dispatch path, in both `ar` and `en`.

**Non-goals (for this plan)**
- Manual/UAT scripts, browser/E2E (Dusk), visual/CSS, and load testing beyond the
  existing `tests/Load` sketch.
- Chasing a coverage percentage. Target behavior, not lines.

---

## 3. Conventions for new tests

- **Extend `EventModuleTestCase`** for anything touching RBAC, courses, users, or
  notifications (it seeds permissions). Extend `Tests\TestCase` only for pure,
  DB-less unit tests.
- **Naming:** `test_<subject>_<condition>_<expected>()`. One behavior per test.
- **Authorization matrix pattern:** for each protected route, assert
  (a) an actor **with** the permission gets `200/redirect`, (b) an actor **without** it
  gets `403`, (c) a guest is redirected to login. Drive permissions through
  `courseRoleWithPermissions()` / system roles — never assert on role *names* (rule 4).
- **Localization:** assert against `__('key')`, never literal Arabic/English strings, so
  tests pass under both locales. Add at least one test per module that sets
  `app()->setLocale('en')` and re-checks a key string, guarding rule 6.
- **Notifications:** always `Mail::fake()` and assert both the `UserNotification` row
  (type + user) **and** `Mail::assertSent(...)->hasTo(...)`. Assert the *absence* of a
  notification on no-op paths (see `RoleAssignmentNotificationTest`).
- **Audit:** any destructive action must assert an `audit_log` row is written (rule 8).
- **Factories:** add missing model factories incrementally under
  `database/factories/` and migrate helper-based creation toward them, but keep the
  `EventModuleTestCase` helpers working — many tests depend on them.
- **No network, no clock drift:** freeze time with `$this->travelTo(...)` for anything
  date-sensitive (attendance late policy, session reminders, birthdays, exam timers).

---

## 4. Workstreams

Each workstream lists the target test files to create, the key assertions, and the
services/controllers under test. Ordered by priority.

### WS-1 — RBAC & Roles Hub (highest priority; has active uncommitted changes)

Controllers: `RolesHubController`, `CourseRoleController`, `SystemRoleController`,
`UserCourseRoleController`, `SuperAdminController`, `RoleController`.
Services: `RolesHubService`, `CourseRoleAssignmentService`, `RoleTemplateService`,
`CoursePermissionResolver`, `RoleAssignmentNotificationService`.

New/expanded tests:
- `Feature/Rbac/PermissionResolverTest` — `CoursePermissionResolver` resolves the
  effective permission set for a user in a course (direct role, template-cloned role,
  superadmin bypass, no-role → empty). This is the linchpin; test it hard.
- `Feature/Rbac/RoleTemplateTest` — cloning a system template into a course copies its
  permissions; editing the course role does **not** mutate the template; `is_template`
  / `is_system` invariants hold.
- `Feature/Rbac/CourseRoleAssignmentTest` — assign / update / revoke; idempotent
  re-assign is a no-op (no duplicate `UserCourseRole`, no re-notify); revoke writes
  audit + notification.
- `Feature/Rbac/SystemRoleTest` — grant/revoke system roles (`UserSystemRole`),
  superadmin-only, self-demotion guard (last superadmin cannot remove own superadmin).
- `Feature/Rbac/RolesHubAuthorizationTest` — extend existing `RolesHubTest`: full
  section-visibility matrix (course-manager vs template-manager vs system-admin vs
  superadmin vs unauthorized), legacy-route redirects, and the email-templates section
  (new `section-email-templates` partial).
- `Feature/Rbac/PermissionSyncTest` — `permissions:sync` is idempotent and every key
  referenced in `config/permissions.php` maps to a `Permission` row after sync.

### WS-2 — Notifications & Email

Services: `NotificationDispatchService`, `NotificationGeneratorService`,
`NotificationFeedService`, `NotificationPreferenceService`, `NotificationScannerService`,
`RoleAssignmentMailService`, `RoleAssignmentNotificationService`,
`RegistrationReviewMailService`, `CourseApplicationMailService`,
`CourseGraduationMailService`, `SessionNotificationService`, `BirthdayNotificationService`,
`WhatsAppNotificationService`.
Mailables: `RoleAssignmentMail` (+ `RoleAssignmentEmailTemplate` model & templates table).

New/expanded tests:
- `Feature/Notifications/DispatchMatrixTest` — for each notification `type`, assert the
  portal row is created for the right recipients and respects
  `UserNotificationPreference` opt-outs.
- `Unit/Notifications/RoleAssignmentMailServiceTest` — renders the DB-backed
  `RoleAssignmentEmailTemplate` with variable substitution; falls back to the default
  template (`resources/views/emails/role-assignment.blade.php`) when none configured;
  renders correctly in `ar` and `en`.
- `Feature/Notifications/EmailTemplateAdminTest` — CRUD on
  `RoleAssignmentEmailTemplate` via the roles-hub section; superadmin-gated; localized.
- Extend `RoleAssignmentNotificationTest` — cover the update path (role changed →
  notify; unchanged → skip, already present) and the revoke path.
- `Unit/Notifications/PreferenceServiceTest` — channel resolution (portal / email /
  WhatsApp) per preference; default when preference row absent.
- `Feature/Notifications/WhatsAppDispatchTest` — `WhatsAppNotificationService` records a
  `NotificationWhatsappDelivery` and never calls the network under test (fake the client).
- Reminder scanning: `SessionUpcomingNotificationTest` exists — add
  `Unit/Notifications/ScannerServiceTest` for the dedupe window (no double-send within
  the reminder window; uses `travelTo`).

### WS-3 — Tenant isolation (forward-looking; build only when Phase lands)

> As of this writing there is **no** `church_id`, `BelongsToChurch`, `app/Tenancy/`, or
> `docs/khedma-master-plan.md` in the repo. This workstream is the scaffold to stand up
> *the same day* the expand migration adds `church_id`. Until then, add only the
> guard test in bullet 1 so the intent is version-controlled.

- `Feature/Tenancy/IsolationTest` (the "sacred suite", CLAUDE.md path
  `tests/Feature/Tenancy/IsolationTest.php`):
  - Seed two churches (`church_id=1` Tenant Zero, `church_id=2`).
  - For **every** tenant-scoped model, assert a query as tenant 2 never returns tenant
    1's rows (global scope from `BelongsToChurch`).
  - Assert `create()` stamps the current `church_id` automatically.
  - Assert cross-tenant direct-id access (route-model binding to another tenant's id)
    returns 404, not 403 (don't leak existence).
  - Assert every `->withoutTenancy()` call site in `app/` has a justifying comment
    (a static guard test that greps the codebase).
- `Unit/Tenancy/TenantContextTest` — context set/clear, missing-tenant behavior when
  `MULTI_TENANT=true`, and full bypass when `MULTI_TENANT=false` (rule 1: zero behavior
  change for Tenant Zero).
- Data-integrity: a test that fails if any new migration adds a tenant-scoped table
  without a `church_id` column (enumerate tables, assert column present) — enforces
  rule 2/3 at CI time.

### WS-4 — Whole-system end-to-end (by module)

For each module below: one happy-path feature test through the controller + a small
authorization matrix + primary validation failures. Prioritize the **untested** modules.

1. **Exams** — `Feature/Exams/ExamBuilderTest`, `ExamAttemptTest`, `ExamGradingTest`,
   `ExamProctoringTest`. Cover: build exam + questions/options; schedule window
   enforcement; attempt start/submit; timer expiry auto-submit (`ExamTimerService`,
   `travelTo`); auto-grade objective + manual essay grading (`ExamGradingService`,
   `EssayGradingService`); proctor event logging (`ExamProctorService`).
2. **Grades** — `Feature/Grades/GradeStructureTest`, `StudentGradeTest`,
   `FinalGradesTest`. Weighted category/item computation; visibility to students only
   when published; `academic.grades` permission gating.
3. **Certificates** — `Feature/Certificates/CertificateIssueTest`,
   `CertificateDownloadTest`. Issued only on graduation criteria met
   (`GraduationService`, `CourseClosingService`); download authorization
   (`CertificateDownloadController`); template rendering.
4. **Curriculum** — `Feature/Curriculum/{Module,Lecture,LectureMaterial,Content}Test`.
   Lifecycle transitions (`enhance_curriculum_module_lifecycle` migration), ordering,
   content feedback.
5. **Assessments** — `Feature/Assessments/AssessmentFlowTest`
   (`Assessment`→`CourseAssessment`→`UserAssessment`).
6. **Attendance** — extend `AttendanceRosterTest`: `Unit/Attendance/LatePolicyTest`
   (`AttendanceLatePolicyService` boundaries with `travelTo`), close/reopen
   (`AttendanceCloseService`), configure permission gating.
7. **Course applications & registration** — extend existing review tests with the
   student-submission side (`CourseApplicationService`,
   `CourseApplicationValidationService`, `CourseApplicationFormService`), multi-step
   form validation, field-level review templates.
8. **Live Quiz** — extend `LiveQuizHostEndTest`: `Feature/LiveQuiz/HostFlowTest`,
   `PlayFlowTest` (join code via `LiveJoinCodeService`, session lifecycle, scoring).
9. **Feedback surveys** — `Feature/Feedback/SurveyFlowTest` (build → publish →
   student submit → report), mandatory-feedback gating (`MandatoryFeedbackService`).
10. **Announcements** — extend `AnnouncementModuleTest`: publish + WhatsApp mark +
    revision history + delivery targeting.
11. **Auth & account** — extend existing: OTP flow (`OTPController`, `OtpCode`),
    impersonation start/stop + audit (`ImpersonationService`), force-logout
    (`ForceLogoutService`), profile-photo gate (`ProfilePhotoGateService`), locale &
    theme switching, font-size preference, login-trial lockout (`LoginTrial`).
12. **SuperAdmin & audit** — `Feature/SuperAdmin/AuditLogTest` (`AuditLogService`
    records every destructive action, rule 8), portal settings, the event-test
    dashboard already covered.

### WS-5 — Service-layer unit tests

Pure-logic services deserve fast unit tests independent of HTTP. Priority targets:
`EventEligibilityService` (done), `EventReservationService` (done),
`CoursePermissionResolver`, `RoleTemplateService`, `AttendanceLatePolicyService`,
`ExamGradingService`, `EssayGradingService`, `GraduationService`,
`NotificationPreferenceService`, `CourseEnrollmentService`, `LiveQuizSessionService`,
`CertificateService`. **Money rule (7):** any service touching amounts must have a test
asserting integer minor units + currency are preserved and no float arithmetic occurs.

---

## 5. Cross-cutting invariant tests (CI guards)

These encode the CLAUDE.md hard rules as executable checks:

| Rule | Guard test |
|---|---|
| 3 — tenancy trait | grep-based test: every model in a tenant-scoped list uses `BelongsToChurch`; every `withoutTenancy()` has an adjacent justifying comment |
| 4 — no role-name checks | grep-based test: fail on `role_name ===`/`->role_name ==` string comparisons in `app/Http/Controllers` |
| 6 — localization | test that every key present in `lang/en/*` also exists in `lang/ar/*` (and vice-versa) |
| 7 — money | grep-based test: no `float`/`(float)` casts around money fields; amounts stored as integers |
| 8 — audit | asserted per-flow in WS-4/WS-1 (destructive action → `audit_log` row) |

---

## 6. Execution & CI

- **Local:** `php artisan test` (pin CLI to PHP 8.2, per CLAUDE.md environment note).
  Filter by suite: `php artisan test --testsuite=Feature`, or by module:
  `php artisan test --filter=Exams`.
- **Suites stay separate:** `Load` must not run in the default PR gate (slow).
- **Add a `composer test` script** mapping to `@php artisan test` so CI has a stable
  entrypoint (none exists today).
- **PR gate (rule 9):** `Unit` + `Feature` must pass on every PR. Once WS-3 lands, the
  `tests/Feature/Tenancy` suite is a required, non-negotiable gate.
- **No npm in CI/deploy** (no build step exists — CLAUDE.md environment note).

---

## 7. Phasing (suggested order of implementation)

1. **Phase A (now):** WS-1 (RBAC/roles hub) + WS-2 (notifications/email) — these have
   active uncommitted changes and the highest regression risk. Add `composer test`
   script and the rule-4/rule-6 CI guards.
2. **Phase B:** WS-4 items 1–3 (Exams, Grades, Certificates) + WS-5 priority services —
   the largest untested surface.
3. **Phase C:** WS-4 items 4–12 — fill remaining module gaps; add factories as you go.
4. **Phase D (gated on migration phase):** WS-3 tenant isolation — stand up the sacred
   suite the day `church_id`/`BelongsToChurch` land. Do not build ahead (rule 10).

---

## 8. Definition of done (per test-writing PR)

- New tests pass locally on PHP 8.2 and green in CI (`Unit` + `Feature`).
- Assertions use `__('key')`, not literal localized strings.
- Notification paths assert both portal row and `Mail::fake`.
- Destructive paths assert an audit row.
- No test hits the network or depends on wall-clock time.
- Existing `EventModuleTestCase` helpers remain intact.

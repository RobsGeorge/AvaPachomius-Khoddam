# Test-case catalog

Each test case references its use case(s) and states how it is covered. Legend:
**✅ automated** (`file::method`) · **🔲 planned** (high-value gap) · **📋 manual** (needs a browser / human judgement).
Priority: **P0** (deploy gate), **P1** (should automate soon), **P2** (nice to have).

## Cross-cutting (authorization & accessibility)

| TC | Covers | Given / When / Then | Type | Status | Pri |
|---|---|---|---|---|---|
| TC-AUTHZ-01 | UC-*-* | Plain approved student → every admin/superadmin/staff GET page → never served 200 | feature | ✅ `UseCases/AuthorizationMatrixTest::test_plain_student_is_denied_privileged_pages` | P0 |
| TC-AUTHZ-02 | UC-AUTH-* | Guest → every privileged GET page → never 200 (redirected) | feature | ✅ `AuthorizationMatrixTest::test_guest_cannot_reach_privileged_pages` | P0 |
| TC-AUTHZ-03 | UC-SA-01 | SuperAdmin → every privileged GET page → not 403/5xx | feature | ✅ `AuthorizationMatrixTest::test_superadmin_can_reach_privileged_pages` | P0 |
| TC-SMOKE-01 | all | Every parameterless GET page renders without 5xx (superadmin) | smoke | ✅ `Smoke/EndpointSmokeTest` | P0 |
| TC-SMOKE-02 | all | Every route resolves to a real controller action; no dupes | smoke | ✅ `Smoke/RouteInventoryTest` | P0 |
| TC-A11Y-01 | a11y | Every 200 page declares `<html lang>`, `dir`, non-empty `<title>` | a11y | ✅ `UseCases/Accessibility/RenderedPageA11yTest::test_every_page_declares_language_direction_and_title` | P1 |
| TC-A11Y-02 | a11y | Every `<img>` on every page has an `alt` attribute | a11y | ✅ `RenderedPageA11yTest::test_all_images_declare_alt_text` | P1 |
| TC-A11Y-03a | a11y (A11Y-01) | Every page exposes a skip-to-content link targeting `#app-main` | a11y | ✅ `RenderedPageA11yTest::test_pages_expose_a_skip_to_content_link` | P1 |
| TC-A11Y-03b | a11y (A11Y-03) | Layout exposes a polite `aria-live` region (JS mirrors toasts into it) | a11y | ✅ `RenderedPageA11yTest::test_pages_expose_a_polite_live_region` | P1 |
| TC-A11Y-03c | a11y (A11Y-02) | Theme CSS honors `prefers-reduced-motion` | a11y | ✅ `RenderedPageA11yTest::test_theme_css_honors_reduced_motion` | P1 |
| TC-A11Y-03 | a11y | Keyboard-only operability, visible focus, full ARIA landmarks (browser) | a11y | 📋 manual (audit) | P1 |
| TC-A11Y-04 | a11y | Color contrast ≥ AA for theme palettes (light/dark) | a11y | 📋 manual (audit) | P1 |
| TC-A11Y-05 | a11y | SR announcements for toasts/live-quiz timers; `prefers-reduced-motion` honored | a11y | 📋 manual (audit) | P2 |
| TC-I18N-01 | UC-AUTH-12 | Every `lang/en/*` file has an `lang/ar/*` counterpart | feature | ✅ `Tenancy/TenantIsolationTest::test_language_files_have_locale_parity` | P1 |
| TC-I18N-02 | UC-AUTH-12 (F-13) | No *new* untranslated key ships (baseline-gated key-level ar/en parity) | feature | ✅ `Localization/LocaleKeyParityTest` | P1 |
| TC-SEC-01 | F-16 | CORS scoped to `api/*`; origins env-driven (no hardcoded localhost default) | manual | 📋 config review (`config/cors.php`) | P2 |

## Positive persona journeys

| TC | Covers | Given / When / Then | Type | Status | Pri |
|---|---|---|---|---|---|
| TC-JOURNEY-01 | UC-EVT-04 | Event admin → `events.admin.index` → 200 | feature | ✅ `UseCases/Journeys/PersonaLandingAccessTest::test_event_admin_reaches_the_event_admin_console` | P1 |
| TC-JOURNEY-02 | UC-SVC-01 | Service member → `services.select` → 200 | feature | ✅ `PersonaLandingAccessTest::test_service_member_reaches_service_context` | P1 |
| TC-JOURNEY-03 | UC-AUTH-08 | Applicant → `application.status` → 200 | feature | ✅ `PersonaLandingAccessTest::test_applicant_can_view_their_application_status` | P1 |
| TC-JOURNEY-04 | UC-CRS-08 | Approved student → `dashboard` → <400 | feature | ✅ `PersonaLandingAccessTest::test_approved_student_reaches_the_portal` | P1 |
| TC-JOURNEY-05 | UC-AUTH-02/03/05 | Register → OTP → set password → login (full flow) | feature | 🔲 planned | P1 |
| TC-JOURNEY-06 | UC-CRS-04/UC-SVC-07 | Applicant approved → enrolled + service member + notified | feature | ✅ `CourseApplicationReviewTest`, `RegistrationApplicationReviewTest` | P1 |
| TC-JOURNEY-07 | UC-CUR/ASG/EXAM/GRD/CERT | Student: curriculum → submit assignment → sit exam → see grade → download certificate | feature | 🔲 planned | P1 |
| TC-JOURNEY-08 | UC-EVT-02/03/06 | Member reserves → waitlist → check-in | feature | ✅ `Events/EventReservationFlowTest`, `EventCheckInFlowTest` | P1 |

## Per-module (automated where marked; else planned/manual)

| TC | Covers | Focus | Status | Pri |
|---|---|---|---|---|
| TC-AUTH-01..12 | UC-AUTH-* | login/register/otp/reset/logout/locale | ✅ `Auth/*Test`, `OtpVerificationTest`, `RegistrationTest`, `PasswordResetTest` | P0/P1 |
| TC-CRS-01..09 | UC-CRS-* | apply/review/approve/build-form/context | ✅ `CourseApplicationReviewTest`, `CourseContextTest`; form-builder edge cases 🔲 | P1 |
| TC-CUR-01..03 / TC-ATT-01..05 | UC-CUR/ATT-* | curriculum lifecycle, attendance record/edit/close, late policy | partial ✅ `AttendanceRosterTest`; late-policy + close/reopen 🔲 | P1 |
| TC-ASG-01..06 | UC-ASG-* | submit (PDF/deadline), grade, manage | partial ✅ `AssignmentCourseContextTest`; submit/grade 🔲 | P1 |
| TC-EXAM-04..06 | UC-EXAM-04/05/06 | objective auto-grade, manual essay finalization, idempotent submit | ✅ `UseCases/Exams/ExamGradingTest` | P1 |
| TC-EXAM-01..03,07,08 | UC-EXAM-* | build/schedule/attempt window/timer auto-submit/proctor/publish-visibility | 🔲 planned (HTTP flow) | P1 |
| TC-GRD-01..07 | UC-GRD/CERT-* | weighted grades, publish visibility, graduation, certificate download | partial ✅ `CourseGraduationClosingTest`; grade calc + cert download 🔲 | P1 |
| TC-EVT-01..07 | UC-EVT-* | eligibility/reserve/waitlist/checkin/admin | ✅ `Events/*`, `Unit/Events/*`, `Load/Events/*` | P1 |
| TC-LQ-01..04 | UC-LQ-* | build/host/join/score/end | partial ✅ `LiveQuizHostEndTest`; join/play 🔲 | P2 |
| TC-FB-01..04 | UC-FB-* | build/respond/report/mandatory-gate | partial ✅ `FeedbackSurveyRouteTest`; flow 🔲 | P2 |
| TC-ANN-01..03 / TC-NOT-01..05 | UC-ANN/NOT-* | announce/publish/whatsapp, feed/read/preferences, email+WhatsApp dispatch | ✅ `NotificationHubTest`, `NotificationActionLinkTest`, `AnnouncementModuleTest`, `RoleAssignmentNotificationTest`, `UseCases/../NotificationDeliveryTest`, `Mail/ExternalCommunicationTest` | P1 |
| TC-RBAC-01..08 | UC-RBAC-* | hub sections, assign/revoke+notify, templates, resolver, preview | ✅ `RolesHubTest`, `DynamicRoleManagementTest`, `RoleAssignmentNotificationTest`, `RolePreviewTest` | P0/P1 |
| TC-SVC-01..07 | UC-SVC-* | context/roster, membership, cross-add, roles, approval auto-enroll | ✅ `ServiceLayerTest`, `CourseApplicationReviewTest` | P1 |
| TC-SA-01..09 | UC-SA-* | console, audit, impersonate, force-logout, translations, photo review, test report | partial ✅ `SuperAdminEventTestsDashboardTest`, `ProfilePhotoAdminTest`, `ImpersonationTest`; audit-on-destroy 🔲 | P1 |
| TC-ACC-01..02,04..06 | UC-ACC-* | onboarding, profile/photo gate, theme/locale/font, preferences | partial ✅ `StudentOnboardingTest`, `ProfilePhotoAdminTest` | P1 |
| TC-ACC-03 | UC-ACC-03 (F-03) | Self-service password change: correct current pw → updated; wrong current pw / weak pw → rejected, unchanged | feature | ✅ `UseCases/Account/AccountCenterTest` | P1 |
| TC-ACC-07 | UC-ACC-* (F-03) | Account center hub renders (profile, security, notifications, appearance, data export); guest blocked; data export returns own JSON | feature | ✅ `UseCases/Account/AccountCenterTest` | P1 |
| TC-DASH-01..03 | UC-CRS-08 (F-01) | Per-persona dashboard focus panel: student sees upcoming exams, reviewer sees application queue, empty user sees no panel | feature | ✅ `UseCases/Dashboard/DashboardFocusTest` | P1 |
| TC-ML-01..03 | UC-GRD/CERT-* (F-02) | "My learning" aggregates per-course grades/attendance/certificate; empty state when unenrolled | feature | ✅ `UseCases/Learning/MyLearningTest` | P1 |
| TC-APP-01..04 | UC-AUTH-08 (F-04) | Applicant status timeline; inline correction guidance lists rejected fields+notes; localized help/FAQ; help requires auth | feature | ✅ `UseCases/Applicant/ApplicantExperienceTest` | P1 |
| TC-CAL-01..04 | UC-CUR/EXAM/EVT (F-06) | Personal iCalendar feed aggregates upcoming sessions/exams/events + course-scoped birthdays (yearly, students+staff, not self/outsiders); `.ics` attachment; requires auth | feature | ✅ `UseCases/Calendar/CalendarExportTest` | P1 |
| TC-AUDIT-01..03 | UC-SA-02 (F-09) | Superadmin exports filtered activity log as CSV; date-range scopes the export; non-superadmin forbidden | feature | ✅ `UseCases/Admin/AuditVisibilityTest` | P1 |
| TC-PRINT-01 | UC-GRD/CERT-* (F-07) | Pages ship a print stylesheet + print affordance (final grades / My learning) | feature | ✅ `UseCases/Admin/AuditVisibilityTest::test_final_grades_page_offers_a_print_button` | P2 |

## How to extend
Add a UC to the relevant `use-cases/*.md`, then a TC row here. Automate P0/P1 cases under
`tests/Feature/UseCases/` (or the module's existing test file), and flip the status to
`✅ file::method`. Keep IDs stable.

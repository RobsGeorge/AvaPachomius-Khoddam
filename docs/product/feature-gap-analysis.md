# Feature-gap analysis (functional)

A prioritized backlog of features that would make the platform genuinely useful to **every** persona.
Nothing here is implemented — this is a ranked menu. Priorities: **P0** (materially blocks a persona
today), **P1** (high value), **P2** (polish). Cross-references `PARKING-LOT.md`.

## P0 — closes a real gap for a whole persona

> **Delivered in M1** (branch `staging`): **F-01**, **F-02**, **F-03** — see status column.

| # | Feature | Personas served | Why it matters | Status |
|---|---|---|---|---|
| F-01 | **Per-persona home dashboard** | all | Everyone lands on a generic hub; there is no "what do I need to do today" view. Students miss due assignments/exams; admins miss pending approvals. | ✅ **M1** — `DashboardService` focus panel (review queue, upcoming exams, upcoming events), persona-gated. Tests `DashboardFocusTest`. |
| F-02 | **Unified "My learning" for students** | Student | Grades, attendance, certificates, and schedule are scattered across modules. A single student view is the core of the product for the largest persona. | ✅ **M1** — `MyLearningService` + `/my-learning` per-course grades/attendance/certificate. Tests `MyLearningTest`. |
| F-03 | **Self-service account center** | all | Password change, profile edit, notification channels, language/theme, and data export are not in one discoverable place. Password-change flow in particular should be first-class. | ✅ **M1** — `AccountController` `/account`: first-class password change, profile/notification/appearance links, JSON data export. Tests `AccountCenterTest`. |

## P1 — high value

> **Delivered on `staging`:** **F-04** (M2), **F-06** (M3) — see the Why/status column.

| # | Feature | Personas | Why / status |
|---|---|---|---|
| F-04 | **Applicant status timeline + inline correction guidance + help/FAQ** | Applicant | ✅ **M2** — status-timeline partial, inline rejected-field guidance on `application.status`, and a localized `/help` FAQ. Tests `ApplicantExperienceTest`. |
| F-05 | **Global search** (users, courses, services, content) | Admins, staff | Navigating by menus does not scale; admins need to jump to a person/course/service fast. |
| F-06 | **Calendar + iCal/Google export** for sessions, exams, events | Student, Instructor | ✅ **M3** — `CalendarService` renders an RFC 5545 `.ics` feed (`/calendar.ics`) of upcoming sessions/exams/events; linked from My learning. Tests `CalendarExportTest`. |
| F-07 | **Print / offline** for certificates, grade reports, attendance reports | Student, Instructor | ✅ **M4** — global `print.css` (media="print") + print buttons on final-grades & My learning. Tests `AuditVisibilityTest`. |
| F-08 | **Admin bulk tooling**: user & enrollment import/export, gradebook export | Course/Service Admin | Onboarding a cohort by hand is slow and error-prone. |
| F-09 | **Audit-log visibility & filters for admins** | SuperAdmin/Admin | ✅ **M4** — added date-range filter + CSV export (filter-aware, streamed) to the activity log. Tests `AuditVisibilityTest`. |
| F-10 | **Notification preference completeness** (per-category, per-channel, digest) | all | Reduce noise; let users pick email vs WhatsApp vs portal per category, with a daily digest option. |
| F-11 | **Empty states & guided onboarding/tooltips** | new users, all | Blank lists give no next action; first-run guidance improves activation. |
| F-12 | **Exam experience hardening**: autosave, connection-loss recovery, accommodations (extra time) | Student | Timed exams with no autosave risk lost work; also the biggest test-coverage gap. |

## P2 — polish / operational

| # | Feature | Personas | Why |
|---|---|---|---|
| F-13 | **Localization completeness enforcement** in CI (key-level ar/en parity) | Arabic users | File-level parity is guarded; extend to key-level so no string ships untranslated. |
| F-14 | **Mobile-first refinements** (nav, tables → cards, tap targets) | Mobile users | App is responsive but data-dense tables need better small-screen treatment. |
| F-15 | **Service application richer form builder** | Service Admin | Currently single-message; parity with course application forms. (PARKING-LOT) |
| F-16 | **Config/security debt** | ops | CORS `Access-Control-Allow-Origin: localhost:3000` leak in prod; make env-driven and lock down. (PARKING-LOT) |
| F-17 | **Nullable profile columns** | ops/tests | `user` NOT NULL profile columns force placeholder data and complicate admin/self-service creation. (PARKING-LOT; migrates to `people` in tenancy phase.) |
| F-18 | **Fresh-environment bootstrap** | ops/tenancy | Migrations cannot bootstrap an empty DB (legacy columns e.g. `roles.course_id`); needed to stand up new staging/tenant churches. |

## Suggested sequencing
1. F-03 account center + F-01 dashboards (touch every persona, reuse existing data).
2. F-02 student "My learning" + F-04 applicant experience (largest personas, retention).
3. F-12 exam hardening + close the exam **test** gap together.
4. F-06/F-07 calendar & print; F-08/F-09 admin tooling.
5. F-16/F-17/F-18 operational/tenancy debt alongside the multi-tenant migration.

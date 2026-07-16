# Student feature matrix (Web → API → Mobile)

Scope: **student persona** capabilities only (permissions from the student role template).  
Staff / admin / Roles Hub / superadmin are **out of scope** here and stay web-first.

Status legend:

| Status | Meaning |
|--------|---------|
| **Done** | Implemented and usable |
| **Partial** | Some coverage; missing important pieces |
| **Planned** | Intended for student mobile parity; not built yet |
| **Web-only** | Intentionally not on mobile (or deferred indefinitely) |
| **N/A** | Not a student capability |

Suggested waves (priority order for API + Expo work):

1. **Wave A** — foundation (auth, notifications, announcements, my attendance, profile basics)  
2. **Wave B** — academic read path (courses context, curriculum, grades, certificates)  
3. **Wave C** — academic write path (assignments submit, exams take, feedback)  
4. **Wave D** — community / ops (events, applications, service apply, live quiz, birthdays)  
5. **Wave E** — polish (notification prefs, OTP/register on-device, deep links, push)

---

## Matrix

| # | Feature | Web (main routes) | Permission keys | `/api/v1` today | Mobile (Expo) today | Wave | Notes |
|---|---------|-------------------|-----------------|-----------------|---------------------|------|-------|
| 1 | Login | `login` | — | **Done** `POST /login` | **Done** Login screen | A | Sanctum bearer token |
| 2 | Logout | `logout` | — | **Done** `POST /logout` | **Done** | A | |
| 3 | Me / profile read | `profile` | — | **Done** `GET /me` | **Partial** (name/email on home) | A | Includes photo status fields |
| 4 | Communication locale | profile / settings | — | **Done** `PUT /me/preferences` | **Planned** | A | `communication_locale` / `locale` |
| 5 | Design tokens / theme | `theme.update`, CSS | — | **Done** `GET /design-tokens` | **Partial** local `tokens.ts` + toggle | A | Prefer fetch tokens at launch |
| 6 | Notification inbox | `notifications.index`, `show` | — | **Done** `GET /notifications`, `GET /notifications/{id}` | **Partial** (embedded on home) | A | Show marks read |
| 7 | Mark notifications read | `notifications.mark-all-read` | — | **Done** mark-all + `POST …/{id}/read` | **Partial** | A | |
| 8 | Notification settings / reminders | `notifications.settings*` | — | **Done** `GET/PUT /notification-settings` | **Planned** | E | Reminder CRUD still web |
| 9 | Announcements inbox | `announcements.index`, `show` | `announcement.view` | **Done** list + `GET /announcements/{id}` | **Partial** | A | |
| 10 | Dismiss announcement banner | `announcements.dismiss-banner` | `announcement.view` | **Done** `POST …/dismiss-banner` | **Planned** | A | |
| 11 | My attendance | `attendance.my` | `attendance.view_own` | **Done** `GET /attendance/mine` | **Partial** | A | |
| 12 | Dashboard / hubs | `dashboard`, `hubs.*` | — | **Done** `GET /dashboard` | **Planned** | B | Aggregate counts + courses |
| 13 | Course context switch | `courses.select*` | `course.access` | **Done** `GET/POST/DELETE /courses/current` | **Planned** | B | Cache-backed for bearer tokens |
| 14 | Available courses + apply | `available-courses.*`, `courses.apply*` | `course.view` | **Partial** list + status | **Planned** | D | Multipart apply forms still web |
| 15 | Registration application status | `application.status`, `edit` | — | **Partial** `GET /registration-application` | **Planned** | D | Edit/resubmit stays web |
| 16 | Curriculum view | `curriculum.show`, `curriculum.index` | `curriculum.view` | **Done** `GET /courses/{id}/curriculum` | **Planned** | B | |
| 17 | Session list (student) | `sessions.index` | `curriculum.view` / access | **Done** `GET /courses/{id}/sessions` | **Planned** | B | Read-only schedule |
| 18 | Own grades | `grades.show` | `grade.view` | **Done** `GET /courses/{id}/grades` | **Planned** | B | |
| 19 | Final grades | `courses.final-grades` | `grade.view` | **Done** `GET /courses/{id}/final-grades` | **Planned** | B | |
| 20 | Certificate download | `certificates.download` | `certificate.download` | **Done** list + download | **Planned** | B | PDF stream |
| 21 | Assignments list / show | `assignments.index`, `show` | `assignment.view` | **Done** | **Planned** | C | |
| 22 | Assignment submit / update | `assignments.submit`, `update-submission` | `assignment.submit` | **Done** submit + update | **Planned** | C | Multipart PDF |
| 23 | Exams list | `exams.index` | `exam.view` | **Done** `GET /courses/{id}/exams` | **Planned** | C | |
| 24 | Take exam | `exams.attempt.*` | `exam.take` | **Planned** | **Planned** | C | Timer / save / proctor — web or later |
| 25 | Events browse | `events.index`, `show` | `events.view` | **Done** | **Planned** | D | |
| 26 | Event reserve / cancel / mine | `events.reserve`, `cancel`, `my-reservations` | `events.reserve` | **Done** | **Planned** | D | |
| 27 | Feedback surveys (student) | `feedback.*` student | `feedback.view` | **Done** list/show/submit | **Planned** | C | |
| 28 | Live quiz play | `live-quiz.*` play | `live_quiz.play` | **Planned** | **Planned** | D | Realtime / Reverb |
| 29 | Service select / apply | `services.select*`, `services.apply*` | `service.view` (membership) | **Done** list/apply/status | **Planned** | D | |
| 30 | Birthdays feed | `students.birthdays` | roster / course access | **Done** `GET /birthdays` | **Planned** | D | |
| 31 | Profile photo upload | `profile.picture.update` | — | **Done** `POST /me/picture` | **Planned** | B | |
| 32 | Locale switch (ar/en) | `locale.switch` | — | **Done** via `/me/preferences` | **Planned** | A | Client applies RTL |
| 33 | Password reset / OTP / register | `password.*`, `otp.*`, `register` | — | **Planned** | **Planned** | E | Can stay web initially |
| 34 | Content feedback | `contents.feedback*` | — | **Planned** | **Planned** | C | Lower priority |
| 35 | Onboarding complete | `onboarding.complete` | — | **Planned** | **Planned** | E | |
| 36 | Design / marketing homepage | `home` | — | **N/A** | **N/A** | — | Auth app entry = login |

---

## Explicitly web-only for students (do not port)

These appear in `web.php` but are **not** student product goals for mobile:

- Announcement **manage / publish**
- Attendance **record / all / report** (staff)
- Exam **builder / grading**
- Assignment **dashboard / grade**
- Roles Hub, translations, registration review queues
- Communications **report** (staff)
- Course closing, email templates, certificate templates
- Event **admin** check-in consoles
- Full multi-step **course application** form submit (API exposes availability + status only for now)

---

## Current API inventory (`routes/api.php`)

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/v1/design-tokens` | Public |
| POST | `/api/v1/login` | Public (throttled) |
| POST | `/api/v1/logout` | Sanctum |
| GET | `/api/v1/me` | Sanctum |
| PUT | `/api/v1/me/preferences` | Sanctum |
| POST | `/api/v1/me/picture` | Sanctum |
| GET | `/api/v1/dashboard` | Sanctum |
| GET | `/api/v1/notifications` | Sanctum |
| GET | `/api/v1/notifications/{id}` | Sanctum |
| POST | `/api/v1/notifications/{id}/read` | Sanctum |
| POST | `/api/v1/notifications/mark-all-read` | Sanctum |
| GET/PUT | `/api/v1/notification-settings` | Sanctum |
| GET | `/api/v1/announcements` | Sanctum |
| GET | `/api/v1/announcements/{id}` | Sanctum |
| POST | `/api/v1/announcements/{id}/dismiss-banner` | Sanctum |
| GET | `/api/v1/attendance/mine` | Sanctum |
| GET | `/api/v1/courses` | Sanctum |
| GET/POST/DELETE | `/api/v1/courses/current` | Sanctum |
| GET | `/api/v1/courses/{id}` | Sanctum |
| GET | `/api/v1/courses/{id}/curriculum` | Sanctum |
| GET | `/api/v1/courses/{id}/sessions` | Sanctum |
| GET | `/api/v1/courses/{id}/grades` | Sanctum |
| GET | `/api/v1/courses/{id}/final-grades` | Sanctum |
| GET | `/api/v1/courses/{id}/assignments` | Sanctum |
| GET | `/api/v1/courses/{id}/exams` | Sanctum |
| GET | `/api/v1/courses/{id}/application` | Sanctum |
| GET | `/api/v1/assignments/{id}` | Sanctum |
| POST | `/api/v1/assignments/{id}/submit` | Sanctum |
| POST | `/api/v1/submissions/{id}` | Sanctum |
| GET | `/api/v1/certificates` | Sanctum |
| GET | `/api/v1/certificates/{uuid}` | Sanctum |
| GET | `/api/v1/events` | Sanctum |
| GET | `/api/v1/events/mine` | Sanctum |
| GET | `/api/v1/events/{id}` | Sanctum |
| POST | `/api/v1/events/{id}/reserve` | Sanctum |
| POST | `/api/v1/events/{id}/cancel` | Sanctum |
| GET | `/api/v1/available-courses` | Sanctum |
| GET | `/api/v1/registration-application` | Sanctum |
| GET | `/api/v1/services` | Sanctum |
| POST | `/api/v1/services/{id}/apply` | Sanctum |
| GET | `/api/v1/services/{id}/application` | Sanctum |
| GET | `/api/v1/feedback/surveys` | Sanctum |
| GET | `/api/v1/feedback/surveys/{id}` | Sanctum |
| POST | `/api/v1/feedback/surveys/{id}/submit` | Sanctum |
| GET | `/api/v1/birthdays` | Sanctum |
| GET | `/api/user` | Sanctum (legacy scaffold) |

---

## Recommended next implementation slice

**Expo Wave A/B screens against the new JSON:**

1. Notifications list + detail, Announcement detail, Attendance history, Profile (photo)  
2. Course picker (`/courses` + `/courses/current`)  
3. Grades + curriculum + certificates  
4. Then assignments / events tabs  

Backend rule: thin `Api\V1` controllers → existing `app/Services/*` + same permission keys as web. No business-logic fork in TypeScript. Still deferred: **exam take**, **live quiz**, **OTP/register**, multi-step **course apply** submit.

---

## How to use this matrix

- Update status cells when shipping API or Expo work.  
- One PR should usually move **one feature row** (or a tight wave slice) across API + mobile + tests.  
- Product “parity” = all **Planned** student rows Done; not cloning every line of `web.php`.

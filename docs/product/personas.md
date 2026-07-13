# Personas

The platform serves several distinct user types. Roles are **dynamic** (RBAC + Roles Hub), so a
real person may combine several personas (e.g. an instructor in one course who is also a service
admin). Authorization is by **permission key**, resolved per course/service by
`App\Services\CoursePermissionResolver`; SuperAdmin bypasses permission checks
(`App\Services\RolePreviewService::superadminBypassesPermissions`).

Permission profiles below are the **system role templates** from
`app/Services/RoleTemplateService.php` (course templates: admin/instructor/student; service
templates: service-admin/service-member).

## Functional personas

### 1. Guest (unauthenticated)
- **Entry:** `/login`, `/register`, `/verify-otp`, `/password/reset`, public course/service apply.
- **Can:** create an account (OTP), request password reset, view the login/registration screens.
- **Refused:** everything behind `auth`. Guests are redirected to `login`.

### 2. Applicant (registered, `application_status != approved`)
- **Middleware:** `RequireApprovedApplication` / `RequireApprovedCourseApplication` bounce them to
  their status page.
- **Can:** complete registration (set password), view application status, respond to correction
  requests, resubmit, edit their profile application.
- **Refused:** course/service content until approved.

### 3. Student (course member — `student` template)
- **Permissions:** `course.view`, `course.access`, `curriculum.view`, `assignment.view`,
  `assignment.submit`, `exam.view`, `exam.take`, `grade.view`, `certificate.download`,
  `attendance.view_own`, `announcement.view`, `feedback.view`, `live_quiz.play`, `events.view`,
  `events.reserve`.
- **Can:** learn — view curriculum, submit assignments, sit exams, see own grades & attendance,
  download certificate, play live quizzes, give feedback, reserve events.
- **Refused:** any management/authoring/grading path.

### 4. Instructor (course staff — `instructor` template)
- **Permissions:** student-teaching set plus `curriculum.manage`, `assignment.manage`,
  `assignment.grade`, `exam.author`, `exam.schedule`, `exam.grade`, `grade.manage`,
  `attendance.record`, `attendance.view_all`, `attendance.report`, `attendance.edit`,
  `announcement.manage`, `announcement.publish`, `roster.view`, `roster.announce`, `session.notify`,
  `graduation.view`, `graduation.configure`, `course.close`, `certificate.manage`, `feedback.manage`,
  `feedback.report`, `live_quiz.host`, `live_quiz.manage`.
- **Can:** teach — build curriculum/sessions, author & grade assignments/exams, manage the
  gradebook, record attendance, run live quizzes, announce, configure graduation.
- **Refused:** course-role assignment/enrollment and application review (course-admin only), and all
  system/service admin.

### 5. Course Admin (`admin` template)
- **Permissions:** the instructor set plus course RBAC: assign users to course roles, review course
  applications, manage course roles/templates within the course, graduation/closing.
- **Can:** everything an instructor can, plus manage who is in the course and in what role, and
  approve course applications (enrolls the applicant — now also grants service membership).

### 6. Service Member (`service-member` template)
- **Permissions:** `service.view`, `announcement.view`.
- **Can:** operate in a service context, see the service roster/announcements. Service membership is
  the prerequisite for course enrollment.

### 7. Service Admin (`service-admin` template)
- **Permissions:** `service.view`, `service.manage`, `service.member.add`, `service.member.remove`,
  `service.member.add_cross`, `service.role.manage`, `service.user.assign_role`,
  `service_application.review`, `service_application.form_builder`, `announcement.view`,
  `announcement.manage`, `announcement.publish`, `roster.view`.
- **Can:** manage service membership (incl. cross-service add), service roles/templates, review
  service applications, target announcements to the service.

### 8. Event Admin (`EventAdmin` + events permissions)
- **Middleware:** `events.admin` (`EventsAdminMiddleware`) — superadmin or an assigned event admin.
- **Can:** create/publish/cancel events, manage reservations & waitlists, run check-in, view the
  event-tests dashboard.
- **Refused:** non-event admin surfaces unless they also hold those roles.

### 9. SuperAdmin (`is_superadmin = true`)
- **Bypasses** all permission checks. Owns the SuperAdmin console: system roles, role templates,
  group visibility, audit log, impersonation, security/session controls, portal settings, event-tests
  dashboard, and the **System testing report** (`/superadmin/system-tests`).

## Cross-cutting (accessibility) personas

These are not roles but user contexts every functional persona may be in; they drive
[accessibility-audit.md](accessibility-audit.md).

- **Arabic / RTL user** — Arabic is primary; UI is RTL-first (`<html dir="rtl">`). Mixed ar/en content
  must render and align correctly.
- **Keyboard-only user** — must reach and operate every control without a pointer; visible focus,
  logical tab order, skip-to-content.
- **Screen-reader user** — needs landmarks, labels, alt text, and announcements for dynamic content
  (notification toasts, live-quiz timers, SweetAlert dialogs).
- **Mobile user** — small-viewport, touch; the app ships responsive Blade (no SPA build step).

## Persona → entry route (quick reference)

| Persona | Primary landing |
|---|---|
| Guest | `login` |
| Applicant | `application.status` |
| Student | `dashboard` / `hubs.academic` |
| Instructor / Course Admin | `hubs.academic` (+ course context) |
| Service Member / Admin | `services.select` / service roster |
| Event Admin | `events.admin.index` |
| SuperAdmin | `superadmin.index` |

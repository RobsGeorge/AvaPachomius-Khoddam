# Use cases — Courses & Course applications

Personas: **Student/Applicant**, **Instructor**, **Course Admin**, **SuperAdmin**.
Controllers: `CourseController`, `CourseContextController`, `CourseApplicationController` (student),
`Admin\CourseApplicationController` + `CourseApplicationFormController` (review/build),
`CourseApplicationReviewService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-CRS-01 | Student | Browse available courses (`available-courses.index`) | Empty state when none open | `course.view` |
| UC-CRS-02 | Student | Apply to a course via its multi-step form → application submitted (`pending`) | Missing required fields → validation; re-apply after edit updates existing draft | Must be service member (guard); `auth` |
| UC-CRS-03 | Student | Track application status; edit/resubmit while `pending`/`needs_correction` | Cannot edit once approved/rejected | Own application |
| UC-CRS-04 | Course Admin | Review queue: accept/reject fields, approve → **student enrolled with the form's default role + enrolled in the course's service** | Approve with rejected fields (unless allowed) → error; missing default role → `default_role_required` | `course_application.review` |
| UC-CRS-05 | Course Admin | Reject / request corrections with note → applicant notified | — | `course_application.review` |
| UC-CRS-06 | Course Admin | Build/enable the per-course application form (steps, fields, default role) | Disable form → hides Apply | `course_application.form_builder` / course admin |
| UC-CRS-07 | Course Admin/SuperAdmin | Create / edit / archive a course; set year, branding, localized titles | Archived/closed course excluded from pickers | `courses` resource; admin |
| UC-CRS-08 | Any staff | Select current course context (`courses.select`); nav & pages scope to it | Superadmin may skip picker | `auth`; course membership/roles |
| UC-CRS-09 | Student | Deep-link to a course route auto-syncs context | Access to a course you're not in → refused | course membership |

**Coverage:** `CourseApplicationReviewTest`, `CourseContextTest`, `StudentOnboardingTest`,
`AuthorizationMatrixTest` (review/build gated), enrollment-on-approval fix in
`CourseApplicationReviewService`.

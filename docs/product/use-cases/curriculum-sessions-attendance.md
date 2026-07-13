# Use cases — Curriculum, Sessions & Attendance

Personas: **Student**, **Instructor**, **Course Admin**, **Attendance staff**.
Controllers: `CurriculumController`, `ModuleController`, `LectureController`,
`LectureMaterialController`, `ContentController`, `SessionController`, `SessionAttendanceController`,
`AttendanceController`, `AttendanceSettingsController`; services `AttendanceCloseService`,
`AttendanceLatePolicyService`, `SessionNotificationService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-CUR-01 | Student | View curriculum for current course (modules → lectures → materials/content) | No enabled form / not enrolled → refused | `curriculum.view` |
| UC-CUR-02 | Instructor | Create/edit/reorder modules & lectures; attach materials & content; manage lifecycle state | Invalid transition blocked | `curriculum.manage` |
| UC-CUR-03 | Instructor | Create sessions (single / multiple dates / weekly auto) with start time | Overlapping/invalid dates validated | `curriculum.manage` |
| UC-SES-01 | Instructor | Notify students of an upcoming session (targets) → notifications dispatched | Dedup within reminder window | `session.notify` |
| UC-ATT-01 | Attendance staff | Open today's sessions; record attendance (present/absent/excused) via roster or QR | Late policy applies per `AttendanceLatePolicyService` | `attendance.record` / `attendance.staff` |
| UC-ATT-02 | Instructor | View all attendance; filter/group by date/session/module; export report | — | `attendance.view_all`, `attendance.report` |
| UC-ATT-03 | Instructor | Edit an attendance record / permission reason; close & reopen a session's attendance | Closed session blocks edits until reopened | `attendance.edit` / `attendance.configure` |
| UC-ATT-04 | Student | View **own** attendance record only | Cannot see others' | `attendance.view_own` |
| UC-ATT-05 | Course Admin | Configure attendance policy (late thresholds, defaults) | — | `attendance.configure` |

**Coverage:** `AttendanceRosterTest`, `SessionUpcomingNotificationTest`,
`Unit/SessionNotificationServiceTest`; management paths gated in `AuthorizationMatrixTest`.
Gaps: student-facing session calendar/iCal (see feature-gap-analysis).

# Use cases — Assignments

Personas: **Student**, **Instructor/Course Admin**. Controllers: `AssignmentController`,
`AssessmentController` (legacy assessments), model `Assignment`, `AssignmentSubmission`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-ASG-01 | Student | View assignments list & detail for current course | Empty state | `assignment.view` |
| UC-ASG-02 | Student | Submit an **online** assignment (content + PDF ≤10MB) before deadline | Non-PDF / oversize → validation; after deadline → blocked (`deadline_passed`); resubmit keeps prior file if none chosen; **offline** → blocked (`assignment_offline_no_upload`) | `assignment.submit`; own submission |
| UC-ASG-03 | Student | View own submission + grade + feedback (including offline receipt) | — | own submission |
| UC-ASG-04 | Instructor | Assignments dashboard: create/edit/delete assignment; set points, due date, resources; **set `delivery_mode` at create only** (`online`\|`offline`) | Mode is read-only on edit | `assignment.manage` |
| UC-ASG-05 | Instructor | Review submissions; grade + leave feedback | Team submissions handled; sets `graded_at` | `assignment.grade` |
| UC-ASG-06 | Instructor | Toggle assignment status/visibility | — | `assignment.manage` |
| UC-ASG-07 | Instructor | **Offline:** mark enrolled student as received (creates submission row, no upload) | Idempotent if already received; online assignments rejected | course manage / grade path |
| UC-ASG-08 | Instructor | **Offline two-step:** grade + feedback only after mark-received | — | `assignment.grade` |
| UC-ASG-09 | Instructor | Status report: remind unsubmitted students (portal notification + email, type `assignment_submission_reminder`) | One reminder per student per assignment per calendar day | `assignment.manage` |

**Coverage:** `AssignmentCourseContextTest`; `OfflineAssignmentTest`; management/grade gated in `AuthorizationMatrixTest`.
Gaps: bulk grading, gradebook export (feature-gap-analysis).

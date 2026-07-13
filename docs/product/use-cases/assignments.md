# Use cases — Assignments

Personas: **Student**, **Instructor/Course Admin**. Controllers: `AssignmentController`,
`AssessmentController` (legacy assessments), model `Assignment`, `AssignmentSubmission`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-ASG-01 | Student | View assignments list & detail for current course | Empty state | `assignment.view` |
| UC-ASG-02 | Student | Submit an assignment (content + PDF ≤10MB) before deadline | Non-PDF / oversize → validation; after deadline → blocked (`deadline_passed`); resubmit keeps prior file if none chosen | `assignment.submit`; own submission |
| UC-ASG-03 | Student | View own submission + grade + feedback | — | own submission |
| UC-ASG-04 | Instructor | Assignments dashboard: create/edit/delete assignment; set points, due date, resources | — | `assignment.manage` |
| UC-ASG-05 | Instructor | Review submissions; grade + leave feedback | Team submissions handled | `assignment.grade` |
| UC-ASG-06 | Instructor | Toggle assignment status/visibility | — | `assignment.manage` |

**Coverage:** `AssignmentCourseContextTest`; management/grade gated in `AuthorizationMatrixTest`.
Gaps: bulk grading, gradebook export (feature-gap-analysis).

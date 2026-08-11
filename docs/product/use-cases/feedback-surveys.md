# Use cases — Feedback Surveys

Personas: **Student** (respond), **Instructor** (build/report). Controllers: `FeedbackHubController`,
`FeedbackSurveyStudentController`, `FeedbackSurveyAdminController`, `FeedbackReportController`;
services `FeedbackSurveyService`, `MandatoryFeedbackService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-FB-01 | Instructor | Build a survey (questions/types); publish | Unpublished not shown to students | `feedback.manage` |
| UC-FB-02 | Student | Complete an assigned survey → submission recorded | Mandatory survey gates other actions until done | `feedback.view` |
| UC-FB-03 | Instructor | View survey report / aggregated results (**anonymous** by default) | Request identity reveal for critical cases | `feedback.report` + `feedback.identity.request` |
| UC-FB-04 | Student | See feedback hub of pending/available surveys | Empty state | `feedback.view` |
| UC-FB-05 | Superadmin | Approve/deny identity reveal (requester-only, time-limited) | Denied / expired | `feedback.identity.reveal` / superadmin |

**Coverage:** `FeedbackSurveyRouteTest`; announce/survey score gate + anonymity/reveal in feature tests. Gated in `AuthorizationMatrixTest`.

**Exam linkage:** Module exam scores stay hidden until staff announce results (`exams.grades.announce`) and the student has submitted every mandatory open/closed survey for that module (per-student). Non-mandatory open surveys never soft-block the portal.

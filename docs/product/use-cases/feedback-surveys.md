# Use cases — Feedback Surveys

Personas: **Student** (respond), **Instructor** (build/report). Controllers: `FeedbackHubController`,
`FeedbackSurveyStudentController`, `FeedbackSurveyAdminController`, `FeedbackReportController`;
services `FeedbackSurveyService`, `MandatoryFeedbackService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-FB-01 | Instructor | Build a survey (questions/types); publish | Unpublished not shown to students | `feedback.manage` |
| UC-FB-02 | Student | Complete an assigned survey → submission recorded | Blocking survey hides that module's exam/project results until done | `feedback.view` |
| UC-FB-03 | Instructor | View survey report / aggregated results (**anonymous** by default) | Request identity reveal for critical cases | `feedback.report` + `feedback.identity.request` |
| UC-FB-04 | Student | See feedback hub of pending/available surveys | Empty state | `feedback.view` |
| UC-FB-05 | Superadmin | Approve/deny identity reveal (requester-only, time-limited) | Denied / expired | `feedback.identity.reveal` / superadmin |

**Coverage:** `FeedbackSurveyRouteTest`; announce/survey score gate + anonymity/reveal in feature tests. Gated in `AuthorizationMatrixTest`.

**Exam linkage:** Module exam scores stay hidden until staff announce results (`exams.grades.announce`) and the student has submitted every **open blocking** survey for **that same module**. An open survey on an earlier module never hides later-module scores and never redirects the student away from exams. Staff choose Blocking or Non-blocking when creating or editing a survey (`is_mandatory`). Non-blocking surveys never hide results.

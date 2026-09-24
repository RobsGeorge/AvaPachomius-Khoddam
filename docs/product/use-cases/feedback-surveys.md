# Use cases — Feedback Surveys

Personas: **Student** (respond), **Instructor** (build/report). Controllers: `FeedbackHubController`,
`FeedbackSurveyStudentController`, `FeedbackSurveyAdminController`, `FeedbackReportController`;
services `FeedbackSurveyService`, `MandatoryFeedbackService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-FB-01 | Instructor | Build a survey (questions/types, anonymous/named, optional block of one exam or project); publish | Unpublished not shown to students | `feedback.manage` |
| UC-FB-02 | Student | Complete an assigned survey → submission recorded | Blocking survey hides that assessment's grade until done; badges show anonymity and what is blocked | `feedback.view` |
| UC-FB-03 | Instructor | View survey report / aggregated results (**anonymous** by default) | Named surveys show names; request identity reveal only when anonymous | `feedback.report` + `feedback.identity.request` |
| UC-FB-04 | Student | See feedback hub of pending/available surveys | Empty state | `feedback.view` |
| UC-FB-05 | Superadmin | Approve/deny identity reveal (requester-only, time-limited) | Denied / expired | `feedback.identity.reveal` / superadmin |

**Coverage:** `FeedbackSurveyRouteTest`; announce/survey score gate + anonymity/reveal in feature tests. Gated in `AuthorizationMatrixTest`.

**Exam / project linkage:** Staff choose Blocking or Non-blocking (`is_mandatory`). A blocking survey must pick one exam or project in the module (`blocks_exam_id` or `blocks_project_assessment_id`). That assessment's score stays hidden until results are announced and the student submits this survey. Other assessments in the same module are not hidden. Legacy blocking rows with no target still hide every assessment on the module. Non-blocking surveys never hide results. Students see anonymous/named and blocked-assessment badges on the survey.

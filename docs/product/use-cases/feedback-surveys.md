# Use cases — Feedback Surveys

Personas: **Student** (respond), **Instructor** (build/report). Controllers: `FeedbackHubController`,
`FeedbackSurveyStudentController`, `FeedbackSurveyAdminController`, `FeedbackReportController`;
services `FeedbackSurveyService`, `MandatoryFeedbackService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-FB-01 | Instructor | Build a survey (questions/types); publish | Unpublished not shown to students | `feedback.manage` |
| UC-FB-02 | Student | Complete an assigned survey → submission recorded | Mandatory survey gates other actions until done | `feedback.view` |
| UC-FB-03 | Instructor | View survey report / aggregated results | — | `feedback.report` |
| UC-FB-04 | Student | See feedback hub of pending/available surveys | Empty state | `feedback.view` |

**Coverage:** `FeedbackSurveyRouteTest`; build/report/mandatory-gating `🔲 planned`. Gated in `AuthorizationMatrixTest`.

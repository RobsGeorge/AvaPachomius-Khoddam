# Use cases — Exams & Proctoring

Personas: **Student**, **Instructor/Course Admin**. Controllers: `ExamController`,
`ExamBuilderController`, `ExamAttemptController`, `ExamGradesController`, `ExamProctorController`(events);
services `ExamGradingService`, `EssayGradingService`, `ExamTimerService`, `ExamProctorService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-EXAM-01 | Instructor | Build an exam: add questions (MCQ/essay) + options + points | — | `exam.author` |
| UC-EXAM-02 | Instructor | Schedule an exam window (open/close times) | Outside window → students blocked | `exam.schedule` |
| UC-EXAM-03 | Student | View available exams for current course | Not scheduled/closed hidden | `exam.view` |
| UC-EXAM-04 | Student | Start an attempt within the window; answer; submit | Timer expiry → auto-submit (`ExamTimerService`); one attempt enforced | `exam.take`; own attempt |
| UC-EXAM-05 | System/Instructor | Objective questions auto-graded; essays manually graded | Mixed exam: partial auto + pending manual | `exam.grade` for manual |
| UC-EXAM-06 | Instructor | View & publish exam grades | Unpublished grades hidden from students | `exam.grade` |
| UC-EXAM-07 | Proctor | Proctoring events logged during attempt (focus/violations) | — | `ExamProctorService` |
| UC-EXAM-08 | Student | View own result after publish | Before publish → not visible | own attempt + published |

**Coverage gap:** no automated exam tests yet — high priority (see test-case-catalog, `🔲 planned`).
Authoring/grading routes gated in `AuthorizationMatrixTest`.

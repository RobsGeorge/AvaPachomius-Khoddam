# Use cases — Student module assessment & instructor notes

Personas: **Instructor** / **Course admin** (assess + notes). Students never see this data.

Controllers: `ModuleStudentAssessmentController`, `StudentInstructorNoteController`;
service `ModuleStudentAssessmentService`.

## Module assessment (weighted rubric)

After a curriculum module is **ended** on `course_module`, staff score each enrolled student on behavioral criteria. Scores are private (not gradebook, not certificates, not student UI/API).

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-SMA-01 | Instructor | Open assessment roster for an ended module | Module still active → 403 | `student_assessment.view` |
| UC-SMA-02 | Instructor | Enter 0–10 scores per criterion; weighted total auto-calculated (0–100) | Draft without all scores; final requires all | `student_assessment.manage` |
| UC-SMA-03 | Instructor | Edit existing assessment for same student/module | Audit log on every change | `student_assessment.manage` |
| UC-SMA-04 | Student | — | Any attempt to read assessment → 403 | Learners never receive keys |

### Scoring model

- Church-scoped `assessment_criteria` (seeded defaults; weights sum to 100)
- Per criterion score: integer **0–10**
- Total: `round(sum(score_i * weight_i) / sum(weights) * 10)` → **0–100**
- One `module_student_assessments` row per `(church_id, course_id, module_id, user_id)`

## Instructor / admin notes (anonymous attribution in UI)

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-SIN-01 | Instructor | Add note in course/module context | Empty body rejected | `student_notes.manage` |
| UC-SIN-02 | Instructor | Read notes across courses/modules in the same church | Filters: module / course / all | `student_notes.view` |
| UC-SIN-03 | Instructor | Timeline without author identity | Date + course + module + body only | `student_notes.view` |
| UC-SIN-04 | Student | — | No access | Learners never receive keys |

**Coverage:** `ModuleStudentAssessmentTest`.

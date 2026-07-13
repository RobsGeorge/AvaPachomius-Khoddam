# Use cases — Grades, Graduation & Certificates

Personas: **Student**, **Instructor/Course Admin**. Controllers: `GradeCategoryController`,
`GradeItemController`, `StudentGradeController`, `FinalGradesController`, `GraduationController`,
`CertificateDownloadController`, `Admin\CourseClosingController`, `Admin\CourseCertificateTemplateController`;
services `GraduationService`, `CourseClosingService`, `CertificateService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-GRD-01 | Instructor | Define grade categories & items with weights | Weights >100% warned | `grade.manage` |
| UC-GRD-02 | Instructor | Enter/adjust student grades; compute weighted totals | — | `grade.manage` |
| UC-GRD-03 | Student | View **own** grades once published | Unpublished hidden | `grade.view`; own |
| UC-GRD-04 | Course Admin | Configure graduation criteria for the course | Nullable criteria allowed | `graduation.configure` |
| UC-GRD-05 | Course Admin | Close/graduate the course → eligible students graduated; emails sent | Criteria unmet → not graduated | `course.close` |
| UC-GRD-06 | Course Admin | Manage certificate template for the course | — | `certificate.manage` |
| UC-CERT-01 | Student | Download own certificate after graduation | Not graduated → refused | `certificate.download`; own |

**Coverage:** `CourseGraduationClosingTest`, `CourseGraduationDebugTest`. Grade-computation and
certificate-download coverage `🔲 planned`. Management gated in `AuthorizationMatrixTest`.

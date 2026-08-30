# Use cases — SuperAdmin console & Audit

Persona: **SuperAdmin** (bypasses permission checks). Controllers: `SuperAdminController`,
`SuperAdminAuditController`, `SuperAdminEventTestController`, `SuperAdminSystemTestController`,
`SuperAdmin\UserDeletionController`, `Admin\TranslationController`, `Admin\ProfilePhotoReportController`;
services `AuditLogService`, `ImpersonationService`, `ForceLogoutService`, `UserDeletionService`,
`SystemTestRunner`, `ProfilePhotoAdminService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-SA-01 | SuperAdmin | Open console (`superadmin.index`); navigate exclusive entry points | Non-superadmin → 403 everywhere | superadmin |
| UC-SA-02 | SuperAdmin | View audit log of destructive actions | — | superadmin |
| UC-SA-03 | SuperAdmin | Impersonate a user; stop impersonation → both audited | — | superadmin |
| UC-SA-04 | SuperAdmin | Security: flush all sessions / force logout | — | superadmin |
| UC-SA-05 | SuperAdmin | Manage translations (ar/en) | — | `translation.manage` |
| UC-SA-06 | SuperAdmin | Review/approve profile photos (gate) | — | `profile_photo.review` |
| UC-SA-07 | SuperAdmin | Run the **System testing report** — categorized pipelines, view results/history | Runs on in-memory sqlite; never touches prod DB | superadmin |
| UC-SA-08 | SuperAdmin | Run the Events-module test dashboard | — | superadmin |
| UC-SA-09 | SuperAdmin | Manage portal settings (theme, profile-photo gate) | — | superadmin |
| UC-SA-10 | SuperAdmin | Search users by name / church / service; soft-delete (optional email or WhatsApp notice); hard-delete with typed-email warning | Cannot delete self or last superadmin; hard delete blocked if FKs remain | superadmin (`platform.users.delete`) |

**Coverage:** `SuperAdminEventTestsDashboardTest`, `ProfilePhotoAdminTest`, `ImpersonationTest`,
`SuperadminUserDeletionTest`; console denial in `AuthorizationMatrixTest`; deletion writes `audit_log`
via `AuditLogService::recordEvent`.

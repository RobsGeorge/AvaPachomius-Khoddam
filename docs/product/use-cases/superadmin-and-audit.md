# Use cases — SuperAdmin console & Audit

Persona: **SuperAdmin** (bypasses permission checks). Controllers: `SuperAdminController`,
`SuperAdminAuditController`, `SuperAdminEventTestController`, `SuperAdminSystemTestController`,
`SuperAdmin\UserDeletionController`, `Admin\TranslationController`, `Admin\ProfilePhotoReportController`;
services `AuditLogService`, `AuditEventGroup`, `ImpersonationService`, `ForceLogoutService`, `UserDeletionService`,
`SystemTestRunner`, `ProfilePhotoAdminService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-SA-01 | SuperAdmin | Open console (`superadmin.index`); navigate exclusive entry points | Non-superadmin → 403 everywhere | superadmin |
| UC-SA-02 | SuperAdmin | View audit log of destructive actions; filter by **event-family group** (auth, password, http, events, sessions, church, people, finance, other); see Auth rollups (logins ok/failed, password changes); export CSV | — | superadmin |
| UC-SA-03 | SuperAdmin | Impersonate a user; stop impersonation → both audited | — | superadmin |
| UC-SA-04 | SuperAdmin | Security: flush all sessions / force logout | — | superadmin |
| UC-SA-05 | SuperAdmin | Manage translations (ar/en) | — | `translation.manage` |
| UC-SA-06 | SuperAdmin | Review/approve profile photos (gate) | — | `profile_photo.review` |
| UC-SA-07 | SuperAdmin | Run the **System testing report** — categorized pipelines, view results/history | Runs on in-memory sqlite; never touches prod DB | superadmin |
| UC-SA-08 | SuperAdmin | Run the Events-module test dashboard | — | superadmin |
| UC-SA-09 | SuperAdmin | Manage portal settings (theme, profile-photo gate) | — | superadmin |
| UC-SA-10 | SuperAdmin | Search users by name / church / service; soft-delete (optional email or WhatsApp notice); hard-delete with typed-email warning | Cannot delete self or last superadmin; hard delete blocked if FKs remain | superadmin (`platform.users.delete`) |

## Audit retention

- `activity_logs` / `login_trials`: pruned daily by `audit:prune` (defaults 90 days; `AUDIT_*_RETENTION_DAYS`).
- Login trials store submitted password values for SuperAdmin investigation (plaintext columns on the Login trials tab).
- First-class password changes emit `auth.password_changed` via `AuditLogService::recordEvent`.
- `access_ledger` is not auto-pruned (tamper-evident).
- Observability prune: `observability:prune` is also scheduled daily.

**Coverage:** `AuditVisibilityTest`, `AccountCenterTest` (password change + no plaintext),
`SuperAdminEventTestsDashboardTest`, `ProfilePhotoAdminTest`, `ImpersonationTest`,
`SuperadminUserDeletionTest`; console denial in `AuthorizationMatrixTest`; deletion writes `audit_log`
via `AuditLogService::recordEvent`.

# Workspace context and View-as (superadmin)

Superadmin operators work from the **Console** (`TENANCY_CONSOLE_HOST`). Church tenant UX is reached deliberately:

| Mode | Entry | Powers |
|------|--------|--------|
| **Console** (default) | Login on console host | Platform registry, audit, security |
| **View as church admin** | Church registry → *View as church admin* | Church-admin template permissions only |
| **View as user** | Security → *View as user* | Auth as target; lands on their church host |
| **View as role** | Security → *View as role* | Permission mask for a course or system role |
| **Platform access** | Church registry → *Open with platform access* | Full superadmin bypass on that church host (breaks-glass) |

Members use the **Workspace** bar (Church → Service → Course). Superadmin sees it only after entering a church workflow above.

Multi-tenant QA with real church-admin accounts: `php artisan demo:seed` — see [demo-data.md](demo-data.md).

Implementation: `PlatformAccessService`, `RolePreviewService::startChurchAdminRole`, `SuperadminWorkspace`, `RedirectSuperadminWithoutTenantWorkflow` middleware.

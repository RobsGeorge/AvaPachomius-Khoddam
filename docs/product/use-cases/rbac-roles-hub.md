# Use cases — RBAC & Roles Hub

Personas: **Course Admin**, **Service Admin**, **SuperAdmin**. Controllers: `RolesHubController`,
`CourseRoleController`, `SystemRoleController`, `UserCourseRoleController`, `RoleController`;
services `RolesHubService`, `CourseRoleAssignmentService`, `RoleTemplateService`,
`CoursePermissionResolver`, `RolePreviewService`, `RoleAssignmentNotificationService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-RBAC-01 | Course Admin | Open Roles Hub scoped to a course; see only permitted sections | Unauthorized → 403; legacy routes redirect to hub | `role.manage` (course) |
| UC-RBAC-02 | Course Admin | Assign a user a course role → user notified (portal + email) | Non-service-member → blocked (must add to service first); duplicate → error flash; re-assign same role → no-op | `role.manage` |
| UC-RBAC-03 | Course Admin | Update/revoke a course-role assignment | Revoke writes audit + notification | `role.manage` |
| UC-RBAC-04 | SuperAdmin | Manage system roles / templates / group visibility | Last-superadmin self-demotion guarded | superadmin |
| UC-RBAC-05 | SuperAdmin | Edit role-assignment email templates (ar/en) | Falls back to default template | superadmin |
| UC-RBAC-06 | SuperAdmin | Clone a system template into a course; edit course role's permissions | Editing course role doesn't mutate template | `role.manage` / superadmin |
| UC-RBAC-07 | Course Admin | Resolve a user's effective permissions in a course (`CoursePermissionResolver`) | Superadmin bypass; no role → empty set | server logic |
| UC-RBAC-08 | SuperAdmin | Preview the portal "as" a role (`RolePreviewService`) | Exits preview | superadmin |

**Coverage:** `RolesHubTest`, `DynamicRoleManagementTest`, `UserCourseRoleIndexTest`,
`RoleAssignmentNotificationTest`, `RolePreviewTest`; denial in `AuthorizationMatrixTest`.

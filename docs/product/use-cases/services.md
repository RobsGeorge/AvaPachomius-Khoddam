# Use cases — Service (department) layer

Personas: **Service Member**, **Service Admin**, **SuperAdmin**. Controllers: `ServiceContextController`,
`ServiceMemberController`, `ServiceRoleController`, `ServiceRosterController`,
`ServiceApplicationController` (+ `Admin\ServiceApplicationController`); services
`ServiceRoleAssignmentService`, `RolesHubService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-SVC-01 | Member | Select/clear service context (`services.select`); view service roster | Not a member → refused | `service.view` |
| UC-SVC-02 | Guest/Applicant | Apply to a service (single-message form) → application recorded | — | public/`auth` per config |
| UC-SVC-03 | Service Admin | Review service applications; approve → applicant becomes member | Reject/correction | `service_application.review` |
| UC-SVC-04 | Service Admin | Add/remove a member; cross-add an existing user from another service | Cross-add needs `service.member.add_cross`; duplicate membership prevented (unique) | `service.member.add`/`remove` |
| UC-SVC-05 | Service Admin | Assign a service role to a member; manage service role templates | member/admin templates auto-provisioned | `service.role.manage`, `service.user.assign_role` |
| UC-SVC-06 | Service Admin | Publish a service-targeted announcement | — | `announcement.manage` (service) |
| UC-SVC-07 | System | Course enrollment requires service membership; course/registration **approval auto-enrolls** the user in the course's service | — | `ServiceRoleAssignmentService::ensureMembershipForCourse` |

**Coverage:** `ServiceLayerTest`, `RoleAssignmentNotificationTest` (guard), enrollment-on-approval in
`CourseApplicationReviewService`/`RegistrationReviewService`; service context reach in `PersonaLandingAccessTest`.

# Use cases — Events & Conferences

Personas: **Any member** (reserve), **Event Admin**, **SuperAdmin**. Controllers: `EventController`,
`EventAdminController`, `EventCheckInController`; services `EventReservationService`,
`EventEligibilityService`, `EventCheckInService`, `EventAdminRoleService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-EVT-01 | Member | Browse events; view detail | Not eligible (visibility/roles) → hidden | `events.view` |
| UC-EVT-02 | Member | Reserve a spot → confirmed, or waitlisted if full | Capacity full → waitlist; ineligible → blocked; duplicate → prevented | `events.reserve` |
| UC-EVT-03 | Member | View my reservations; cancel → waitlist promotion cascades | — | own reservation |
| UC-EVT-04 | Event Admin | Create/publish/cancel an event (capacity, visibility, eligible roles) | Cancel notifies reservees | `events.admin` |
| UC-EVT-05 | Event Admin | Manage reservations & waitlist; promote/demote | — | `events.admin` |
| UC-EVT-06 | Event Admin | Check-in attendees via token/QR | Invalid/expired token → refused | `events.admin` / check-in verify |
| UC-EVT-07 | SuperAdmin | Assign/revoke event admins; run event-tests dashboard | — | superadmin |

**Coverage:** `Events/EventAdminFlowTest`, `Events/EventReservationFlowTest`,
`Events/EventCheckInFlowTest`, `Events/SuperAdminEventTestsDashboardTest`,
`Unit/Events/*`, `Load/Events/EventReservationLoadTest`; admin console reach in
`PersonaLandingAccessTest`.

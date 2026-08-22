# Use cases — Team projects (module-linked assessment)

Personas: **Student**, **Instructor / Course Admin**. Controllers: `ProjectController`,
`ProjectAdminController`; services `ProjectAssignmentService`, `ProjectNotificationService`.

A **Project** is a team assessment attached to a course module the same way an exam is
(`course_id` + `module_id`). It is **not** an `Assignment` (individual homework). Each
project row is one team/topic. Min/max team size live on the parent **project assessment**.

## Settled product decisions

| Topic | Decision |
|---|---|
| Container | `project_assessments` linked to one course + one module (required, like exams). |
| Team = project | One `projects` row is one team. Admin creates 1..N. Same brief can be copied onto many teams. |
| Fill algorithm | **Pack-then-open:** never start an empty team while a started team is below `max_team_size`. Among started teams, prefer the fullest; ties broken randomly. |
| Team complete | Team **closes** when active members = `max_team_size`. Confirmation email/portal notification lists all members + phones + project URL. |
| Below min | Teams under `min_team_size` stay open (not complete). Admin is expected to create enough teams (`ceil(enrollment / max)`). |
| No seats | Join fails if every team is at max. Admin must add another project/team. |
| Publish | Students only see **published** assessments. Join is allowed while published. |
| Phone | `user.mobile_number`; missing shown as localized “not provided”. |
| Change chance | **One approved** reassignment per student per assessment. Rejected requests do not consume the chance. One pending request at a time. |
| Reassignment | Approved student is packed onto a **different** open team. Cannot return to the team they left. Approval fails (and stays pending) if no other seat exists. |
| Leave | Membership `left`; seat frees; closed team reopens. No “uncomplete” email. |
| Grading / file submit | **Out of v1** — parked (`PARKING-LOT.md`). |
| Auth | Permission keys only: `project.view`, `project.join`, `project.manage`. Capability `projects`. |
| Tenancy | Every table has `church_id` + `BelongsToChurch`. |

## Use cases

| UC | Persona | Main path | Alternate / error paths | Authorization |
|---|---|---|---|---|
| UC-PRJ-01 | Instructor | Create a project assessment on a module: title, min/max, 1..N teams, shared requirements / phases / deliverables | Module not in course → validation; max < min → validation | `project.manage` |
| UC-PRJ-02 | Instructor | Publish assessment; edit brief; add extra teams; delete empty unpublished assessment | Delete blocked when any membership exists | `project.manage` |
| UC-PRJ-03 | Student | See published assessments for the current course | Unpublished hidden; other course hidden by context | `project.view` |
| UC-PRJ-04 | Student | Click **Get assigned** → packed onto a team; email/portal notice (first-member vs teammates list) | Already assigned → 409; no seats → validation; unpublished → 404 | `project.join` |
| UC-PRJ-05 | Teammates | Existing members notified of a new teammate (name + phone) | First member: only the “you are first” notice | recipient-scoped |
| UC-PRJ-06 | Team | When the last seat fills, **all** members get a completion notice + full roster + project link | — | recipient-scoped |
| UC-PRJ-07 | Student | Open own project page: title, requirements, phases, deadlines, deliverables, teammates, remaining seats | Other teams’ briefs hidden from students | `project.view`; own membership |
| UC-PRJ-08 | Student | Request a team change with a reason | Second pending blocked; chance already used → blocked | `project.join` |
| UC-PRJ-09 | Instructor | Approve / reject change request | Approve reassigns to another team; no other seat → error, request stays pending | `project.manage` |
| UC-PRJ-10 | Instructor | Review roster: fill counts, remaining seats, pending change requests | — | `project.manage` |

## Pack-fill algorithm

```
open = teams where active_members < max
started = open where active_members > 0
empty = open where active_members == 0

if started is not empty:
    pick uniformly at random among started teams with the highest member count
else:
    pick uniformly at random among empty teams
```

This finishes one team up to `max` before opening the next empty team.

## Notifications (portal + email, academic category)

| Type | Audience | When |
|---|---|---|
| `project_assigned` | student | Joiner: first-member copy **or** current teammates + phones |
| `project_teammate_joined` | student | Existing members when someone new joins |
| `project_team_completed` | student | All members when team reaches max |
| `project_change_requested` | instructor, admin | Staff with `project.manage` on that course |
| `project_change_decided` | student | Approve or reject |

## Coverage

`UseCases/Projects/ProjectAssignmentFlowTest`, `Unit/ProjectAssignmentServiceTest`,
`Tenancy/ProjectIsolationTest`.

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
| Team = unique subproject | One `projects` row is one team **and** one unique title/topic. Shared brief (phases, deliverables, default requirements) is copied onto every team. Optional per-team requirements override the shared text. |
| Fill algorithm | **Pack-then-open:** never start an empty team while a started team is below `max_team_size`. Among started teams, prefer the fullest; ties broken randomly. |
| Team complete | Team **closes** when active members = `max_team_size`. Confirmation email/portal notification lists all members + phones + project URL. |
| Below min | Teams under `min_team_size` stay open (not complete). Admin is expected to create enough teams (`ceil(enrollment / max)`). |
| No seats | Join fails if every team is at max. Admin must add another project/team. |
| Publish | Students only see **published** assessments. Join is allowed while published. |
| Phone | `user.mobile_number`; missing shown as localized “not provided”. |
| Change chance | **One approved** reassignment per student per assessment. Rejected requests do not consume the chance. One pending request at a time. |
| Reassignment | Approved student is packed onto a **different** open team. Cannot return to the team they left. Approval fails (and stays pending) if no other seat exists. |
| Leave | Membership `left`; seat frees; closed team reopens. No “uncomplete” email. |
| Grading | Group grade is the default. Admin scores each team (per criterion or a single total). Optional per-student override. Assessment has `max_points` + `passing_percent`. |
| Announce | Results announced **once** (`results_announced_at`). Grade edits remain allowed after announce. |
| Student visibility | Same gate as exams: announced **and** mandatory module feedback submitted. |
| Auth | Permission keys: `project.view`, `project.join`, `project.manage`, `project.grade`. Capability `projects`. |
| Tenancy | Every table has `church_id` + `BelongsToChurch`. |

## Use cases

| UC | Persona | Main path | Alternate / error paths | Authorization |
|---|---|---|---|---|
| UC-PRJ-01 | Instructor | Create a project assessment on a module: title, min/max, 1..N **unique** subproject titles, shared requirements / phases / deliverables | Module not in course → validation; max < min → validation; duplicate subproject title → validation | `project.manage` |
| UC-PRJ-02 | Instructor | Publish assessment; edit brief; add extra teams; delete empty unpublished assessment | Delete blocked when any membership exists | `project.manage` |
| UC-PRJ-03 | Student | See published assessments for the current course | Unpublished hidden; other course hidden by context | `project.view` |
| UC-PRJ-04 | Student | Click **Get assigned** → packed onto a team; email/portal notice (first-member vs teammates list) | Already assigned → 409; no seats → validation; unpublished → 404 | `project.join` |
| UC-PRJ-05 | Teammates | Existing members notified of a new teammate (name + phone) | First member: only the “you are first” notice | recipient-scoped |
| UC-PRJ-06 | Team | When the last seat fills, **all** members get a completion notice + full roster + project link | — | recipient-scoped |
| UC-PRJ-07 | Student | Open own project page: title, requirements, phases, deadlines, deliverables, teammates, remaining seats | Other teams’ briefs hidden from students | `project.view`; own membership |
| UC-PRJ-08 | Student | Request a team change with a reason | Second pending blocked; chance already used → blocked | `project.join` |
| UC-PRJ-09 | Instructor | Approve / reject change request | Approve reassigns to another team; no other seat → error, request stays pending | `project.manage` |
| UC-PRJ-10 | Instructor | Review roster: fill counts, remaining seats, pending change requests | — | `project.manage` |
| UC-PRJ-11 | Instructor | Set max grade, passing %, and one or more grading criteria | Criteria sum becomes `max_points` | `project.grade` |
| UC-PRJ-12 | Instructor | Enter a **team** grade (per criterion or total); all active members inherit it | Members with an individual override are left unchanged | `project.grade` |
| UC-PRJ-13 | Instructor | Override or revert one student's grade | Not assigned → validation | `project.grade` |
| UC-PRJ-14 | Instructor | Announce grades once | Second announce → 409 | `project.grade` |
| UC-PRJ-15 | Student | See own points / percent / pass-fail after announce + required module feedback | Hidden while pending announcement or pending feedback | `project.view` |
| UC-PRJ-16 | Instructor | Add or rename a team’s unique subproject title | Duplicate (case-insensitive) → validation | `project.manage` |

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

## Unique subprojects

The assessment is the shared project (rubric, passing bar, shared phases /
deliverables). Each `projects` row is one team **and** one unique topic.

- Admin lists distinct titles at create (and can add/rename later).
- Titles are unique per assessment (trimmed, case-insensitive).
- Pack-fill still assigns students to teams. Opening an empty team therefore
  assigns that student a remaining unused subproject at random.
- Students see the assessment name plus their team’s subproject title only.

## Notifications (portal + email, academic category)

| Type | Audience | When |
|---|---|---|
| `project_assigned` | student | Joiner: first-member copy **or** current teammates + phones |
| `project_teammate_joined` | student | Existing members when someone new joins |
| `project_team_completed` | student | All members when team reaches max |
| `project_change_requested` | instructor, admin | Staff with `project.manage` on that course |
| `project_change_decided` | student | Approve or reject |

## Coverage

`UseCases/Projects/ProjectAssignmentFlowTest`, `UseCases/Projects/ProjectGradingTest`,
`Unit/ProjectAssignmentServiceTest`, `Unit/ProjectGradingServiceTest`,
`Tenancy/ProjectIsolationTest`.

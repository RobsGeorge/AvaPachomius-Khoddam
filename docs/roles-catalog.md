# Canonical Role Catalog — Deaconia / Khedma

**Status:** Draft for review · **Source of truth:** this file + `config/permissions.php`
**Derived from:** `app/Services/RoleTemplateService.php`, `config/permissions.php` (verify against code before committing)

This is the **canonical, default set of roles** shipped with every church, service, and
course. It is the single vocabulary the UI, onboarding, tests, and documentation should
speak. Custom roles (created via the Roles Hub) are an *advanced, gated* capability layered
on top of this catalog — see [ADR-0001](adr/0001-rbac-static-catalog-vs-dynamic.md).

**Invariant:** authorization is always **permission-key based** — no code ever checks a
role *name* (CLAUDE.md rule 4). Roles are just named bundles of permission keys. Renaming a
role never changes behavior; only its permission set does.

---

## Scopes at a glance

| Scope | Cloned into… | When | Template source |
|---|---|---|---|
| **Platform** | n/a (a user flag) | — | `is_superadmin` on `user` |
| **Church** | each church | provisioning (T4) | `ensureChurchTemplates()` |
| **Service** | each service | service creation | `ensureServiceTemplates()` |
| **Course** | each course | course creation | `ensureSystemTemplates()` |

Templates live as `is_template = true`, `church_id = null` rows and are **cloned** (not
referenced) into each tenant, so a church may later tune its own copy without affecting others.

---

## Platform scope

| Role | Mechanism | Who it's for | Powers |
|---|---|---|---|
| **Superadmin** | `is_superadmin = true` (flag, not a template) | Platform operators | Bypasses permission checks; holds the `platform.*` (`system_only`) keys: church CRUD, impersonation, audit, session flush, service CRUD, role templates, group visibility, people merge, user deletion. |

> System-wide roles can also be created dynamically (`SystemRoleController`) to delegate
> specific `system`-scoped keys without full superadmin. That is part of the **advanced**
> layer and should be gated per ADR-0001.

---

## Church scope

Cloned into every church at provisioning. `church-admin` additionally absorbs the permission
keys unlocked by the church's enabled **capabilities**.

| Role | Slug | Who it's for | Permission keys |
|---|---|---|---|
| **Church Admin** | `church-admin` | Runs the church | `church.configure`, `church.members.manage`, `church.role.manage`, `priest.manage`, `priest.view`, `confession.manage`, `confession.manage_delegated`, `confession.view`, `confession.book`, `confession.book_on_behalf`, `appointment.manage`, `appointment.manage_delegated`, `appointment.view`, `appointment.book`, `appointment.book_on_behalf`, `home_visit.manage`, `home_visit.view`, `finance.payroll.manage`, `finance.payroll.view`, `finance.money_in.manage`, `finance.money_in.view`, `role.manage`, `user.assign_role`, `announcement.view`, `announcement.manage`, `announcement.publish`, `communications.report`, `roster.view`, `roster.announce`, `service.view`, `service.manage` *(+ capability-enabled keys)* |
| **Priest** | `priest` | Pastoral care | `priest.view`, `confession.manage`, `confession.view`, `appointment.manage`, `appointment.view`, `home_visit.manage`, `home_visit.view`, `announcement.view`, `roster.view` |
| **Secretary** | `secretary` | Priest calendar assistant (delegated) | `priest.view`, `confession.view`, `confession.manage_delegated`, `confession.book_on_behalf`, `appointment.view`, `appointment.manage_delegated`, `appointment.book_on_behalf`, `announcement.view`, `roster.view` — *also requires an active `priest_secretary` row for the target priest* |
| **Servant** | `servant` | Congregation members / servants | `confession.view`, `confession.book`, `appointment.view`, `appointment.book`, `home_visit.manage`, `home_visit.view`, `announcement.view`, `roster.view` |

---

## Service scope

Cloned into every service. Only `service`/`both`/`system`-scoped permission keys survive the
clone (service roles can't hold course-only powers).

| Role | Slug | Who it's for | Permission keys |
|---|---|---|---|
| **Service Admin** | `service-admin` | Runs a service | `service.view`, `service.manage`, `service.member.add`, `service.member.remove`, `service.member.add_cross`, `service.role.manage`, `service.user.assign_role`, `service_application.review`, `service_application.form_builder`, `announcement.view`, `announcement.manage`, `announcement.publish`, `communications.report`, `roster.view` |
| **Service Member** | `service-member` | Belongs to a service | `service.view`, `announcement.view` |

---

## Course scope

Cloned into every course. `admin` is computed (all non-`system_only`, `course`/`both`-scoped
keys); `instructor` and `student` are explicit lists.

| Role | Slug | Who it's for | Permission keys |
|---|---|---|---|
| **Admin** | `admin` | Owns a course | *All non-system permissions in `course` + `both` scopes* (computed) |
| **Instructor** | `instructor` | Teaches a course | `course.access`, `curriculum.view`, `curriculum.manage`, `assignment.view`, `assignment.manage`, `assignment.grade`, `project.view`, `project.manage`, `project.grade`, `exam.view`, `exam.author`, `exam.schedule`, `exam.grade`, `grade.view`, `grade.manage`, `attendance.record`, `attendance.view_all`, `attendance.report`, `attendance.edit`, `announcement.view`, `announcement.manage`, `announcement.publish`, `communications.report`, `roster.view`, `roster.announce`, `session.notify`, `graduation.view`, `graduation.configure`, `course.close`, `certificate.manage`, `feedback.view`, `feedback.manage`, `feedback.report`, `live_quiz.play`, `live_quiz.host`, `live_quiz.manage`, `events.view`, `events.reserve` |
| **Student** | `student` | Enrolled learner | `course.view`, `course.access`, `curriculum.view`, `assignment.view`, `assignment.submit`, `project.view`, `project.join`, `exam.view`, `exam.take`, `grade.view`, `certificate.download`, `attendance.view_own`, `announcement.view`, `feedback.view`, `live_quiz.play`, `events.view`, `events.reserve` |

---

## Known inconsistencies to resolve (part of adopting this catalog)

1. **Naming is not uniform.** Church/service roles are scope-prefixed (`church-admin`,
   `service-admin`) but course roles are bare (`admin`, `instructor`, `student`). Decide
   whether to canonicalize course slugs to `course-admin` / `course-instructor` /
   `course-student`. *Caution:* slugs appear in seeds/tests/possibly URLs — treat any rename
   as an expand-contract migration, not an in-place edit.
2. **"Servant" carries a `home_visit.manage` grant** — confirm that a plain servant should be
   able to *manage* home visits (vs only view). Likely should be `home_visit.view` only.
3. **`church-admin` absorbs capability-enabled keys at clone time** — document which
   capabilities map to which keys so the effective church-admin set is predictable.
4. **The word "role" is overloaded** in the UI between *template* and *tenant copy*. Pick one
   user-facing term for each (e.g. "role" for the tenant copy, "role template" for the seed).

---

## How to use this catalog

- **UI / onboarding:** present these roles by name; assigning a person a role = assigning the
  cloned tenant copy.
- **Docs & test plans:** refer to roles by the names above (Church Admin, Instructor, …) so
  every artifact speaks one vocabulary.
- **Adding a permission:** add the key to `config/permissions.php`, run
  `php artisan permissions:sync`, then add it to the relevant template(s) here + in
  `RoleTemplateService`. Re-provisioned tenants pick it up; existing tenants need a backfill.
- **Custom roles:** allowed only where the church has the advanced role-editing capability
  enabled (ADR-0001). They never introduce new *permission keys* — only new bundles.

# Khedma Master Plan — multi-church platform

> **Status:** active roadmap. **T0–T3 landed** (dormant while `MULTI_TENANT=false`). This is the
> top-level source of truth referenced by `CLAUDE.md`. It sits *above* the detailed tenancy
> engineering spec in [`docs/architecture/multi-subsidiary/`](architecture/multi-subsidiary/README.md)
> and adds the **church-domain** requirements that generic spec does not cover.
>
> Read order for any structural work: **this file → the phase doc in
> `architecture/multi-subsidiary/` → build.** Do not build ahead of §7's current phase.

---

## 1. Purpose & what this is

The live platform at **avapakhomios.com** (AvaPachomius Khoddam) is being evolved, in place,
from a single-institution servants-prep system into a **multi-church management platform**
("Khedma"). AvaPachomius becomes **Church #1 / Tenant Zero** — all existing data is backfilled
into it and it must keep working byte-for-byte at every step (`CLAUDE.md` rule 1).

Two bodies of work meet here:

- **Tenant infrastructure** (isolation, request→tenant resolution, per-tenant roles, provisioning) —
  already fully specified as the *multi-subsidiary* architecture (P0–P6). **We reuse it as-is.**
- **Church domain** (church management module, priests & confession calendars, home visits,
  finance, church registration/approval, polymorphic applications) — **specified here for the
  first time**, layered on top of the infrastructure.

## 2. Vision & hierarchy

```
Platform (superadmin / console)
  └── Church            ← the tenant (isolation boundary). "Church" = "subsidiary".
        ├── Church management module   (priests, confession calendars, home visits, finance)
        └── Service (department)        ← owns membership / RBAC / org
              └── Course                ← owns attendance, grades, exams, lectures, graduation
```

- A church has **many services**; a service has **many courses** (the Service→Course layer
  already exists — see `PARKING-LOT.md` "Service above Course").
- **Every church is accessible only by its own church admins.** No user from one church can read
  or write another church's data. This is the sacred isolation boundary (§14, rules 1–3).

## 3. Terminology & the naming decision  ⚠️ DECISION NEEDED

The infrastructure spec calls the tenant a **subsidiary** (`subsidiary`, `subsidiary_id`,
`BelongsToSubsidiary`, `subsidiary_user`). `CLAUDE.md` calls it a **church** (`church_id`,
`BelongsToChurch`). They are the **same concept**. Before P0 we must pick ONE naming for the
codebase and use it everywhere. Options:

- **(A) Church-native** — `church`, `church_id`, `BelongsToChurch`. Matches the product and
  `CLAUDE.md`'s `TenantIsolationTest` (which checks `church_id` + `App\Tenancy\BelongsToChurch`).
  Cost: the multi-subsidiary docs/migration snippets use `subsidiary` and would be renamed.
- **(B) Subsidiary-generic** — keep `subsidiary_*` (supports future non-church tenants), and make
  "Church" purely a UI label. Cost: `CLAUDE.md` + `TenantIsolationTest` must be updated to match.
- **(C) Church table, generic scope** — tenant table `churches`, trait `BelongsToChurch`, but keep
  the neutral membership/settings machinery. Recommended: aligns with `CLAUDE.md` as written while
  keeping the proven infra design.

This plan is written in **church** terms (assuming A/C). Wherever it says "church," the
infrastructure docs say "subsidiary." **No table is renamed until this is decided.**

## 4. Relationship to the multi-subsidiary spec (do not duplicate)

The following are **already designed and locked** in `architecture/multi-subsidiary/` — we adopt
them unchanged and do **not** re-decide them here:

| Concern | Decision (source of truth) |
|---|---|
| Data isolation | `church_id` on every tenant table + a `BelongsToChurch` global Eloquent scope; only superadmin bypasses. (`P0-foundation.md`, `P1-scoping-resolution.md`) |
| Request → tenant | **Subdomain** `{slug}.<domain>` (+ optional custom domain); leaves existing routes/`route()` calls untouched. (`README.md`, `P4-subdomains-live.md`) |
| User identity | Shared global user pool + `church_user` membership; one login, member of many churches. (`P0-foundation.md`) |
| Roles/permissions | Per-church roles + platform templates; dynamic role→permission grants, code-defined permission keys. (`P3-roles-permissions.md`) |
| Capabilities | Per-church feature switches (a church may not have exams/attendance/etc.). (`P2-capabilities.md`) |
| Provisioning | Superadmin self-service to create/customize churches. (`P5-provisioning-customization.md`) |
| Migrations | Idempotent via `SchemaGuards`/`MigrationSupport`; nullable `church_id` first, backfill, then `NOT NULL` in P1. |

**Gap to fix in that spec:** its `tenancy.tenant_tables` list predates the Service layer. The
**Service tables** (`service`/`ChurchService`, `user_service_role`, service applications) and the
new **church-domain tables** (§8–§11) must be added to the tenant boundary so they carry
`church_id` + the global scope.

## 5. Tenancy model (summary — full detail in the P0–P6 docs)

- **Isolation:** every church-scoped model uses `BelongsToChurch` (global scope). A query that must
  cross the boundary uses an explicit `->withoutTenancy()` **with a justifying comment** (rule 3).
- **Resolution:** `ResolveTenant` middleware maps the request host → church → `TenantContext`;
  a **membership gate** at login prevents access to a church you don't belong to.
- **Flag:** `MULTI_TENANT=false` in production until cutover; every phase keeps the app working with
  the flag off (Tenant Zero behaves exactly as today).
- **Superadmin/console:** cross-church oversight lives on the console host (`admin.<domain>`), not a
  church row; superadmin bypasses the scope.

## 6. Church-domain data model (new — church management module)

All tables below are **church-scoped** (carry `church_id`, `BelongsToChurch` global scope, indexed).
New tables use the `id('x_id')` PK convention; additions to existing tables are idempotent. Shapes
are indicative — finalized per phase.

- **`church`** (a.k.a. subsidiary): `church_id, slug, name, domain?, status, settings(json), timestamps`.
- **`church_user`** membership: `church_user_id, church_id, user_id, status, joined_at` (unique church+user).
- **Church management module** (§8) is a *capability*; its sub-modules:
  - **`priest`** (§9): links a user as a priest of a church — `priest_id, church_id, user_id, title, status`.
  - **`confession_slot`** (§9): priest-owned availability — `slot_id, church_id, priest_id, starts_at,
    ends_at, recurrence?, capacity(default 1), location?, status`. A **booking** table if the served
    reserve slots — `confession_booking_id, slot_id, church_id, user_id, status, notes?`.
  - **`home_visit`** (§10): `home_visit_id, church_id, assigned_user_id (priest/servant), subject_name,
    address?, scheduled_at, duration_min?, status, notes?`.
  - **Finance** (§11): `payroll_run`, `payroll_line`, `money_in` — **money as integer minor units +
    currency + fx_rate**, never floats (rule 7).

## 7. Current phase & roadmap  ← CLAUDE.md reads this section

**Current phase: T8 in progress (expand).** T0–T7 landed. **T8a** landed: structure
templates + anchors, `service` slug/template binding, `service_units` dual-write from courses,
Tenant Zero service slug `servants-prep` + `educational_standard`. **T8b** (this track):
`/{service:slug}` hub + legacy numeric 301s, `enrollments` dual-write, attendance `lock_version`,
nav filtered by structure anchors. Keep `MULTI_TENANT=false` in production until staging pilot
is signed off. Polymorphic applications / public church-registration remain parked (§13 / §17.4).

**Do not build ahead of the phase you are in.** Phase order (each its own PR, app works at every step):

| Phase | Title | Behavior | Church-domain content |
|---|---|---|---|
| **T0** *(=P0)* | Foundation: church tables + backfill | none | `church`, `church_user`; nullable `church_id` on tenant + **service** tables; backfill to Church #1 |
| **T1** *(=P1)* | Scoping & resolution | isolation enforced | `BelongsToChurch`, `TenantContext`, `ResolveTenant`, membership gate; **`TenantIsolationTest` goes green** |
| **T2** *(=P2)* | Capabilities | features toggleable | "Church management" becomes a per-church capability |
| **T3** *(=P3)* ✅ | Roles & permissions | permission-based | church-admin / priest / servant roles + permission keys; capability→permission ceiling |
| **T4** *(=P4/P5)* ✅ | Subdomains + provisioning | real tenants | Superadmin church CRUD + switcher; **church registration / polymorphic apps deferred** |
| **T5** ✅ | Church management module | new feature | priest **confession calendars** (§9), **home-visit schedules** (§10) |
| **T6** ✅ | Financial module | new feature | payroll + money-in (§11), integer minor units |
| **T7** *(contract)* ✅ | Cutover | `MULTI_TENANT=true` (staging) | `NOT NULL church_id`, second church pilot (P6) |
| **T8** *(expand)* | Structure templates + service wrap | template-driven levels | **T8a:** templates/anchors/`service_units`/`servants-prep`. **T8b:** slug routes, enrollments, attendance lock |

Rule 10: anything requested that is ahead of the current phase goes to `PARKING-LOT.md`, not code.

**T8a (landed):** `structure_templates` seeded (`educational_standard`, `meeting_flat`,
`care_sector`), `StructureAnchorResolver`, expand `service` (`slug`, `structure_template_id`,
level overrides), `service_units` dual-write from `course`, Tenant Zero default service →
`servants-prep` + educational template.

**T8b (landed / landing):** slug route key + `/s/{service}` hub + numeric→slug 301s;
`enrollments` table dual-write from `user_course_role` (UCR still source of truth for reads);
attendance `lock_version` CAS; NavigationHub incremental filter via structure anchors.
Detail / residual in `PARKING-LOT.md`.

## 8. Church management module

A per-church capability (T2) that hosts the church-admin tools: priests & confession calendars,
home-visit scheduling, and finance. Gated by capability (disabled ⇒ 404) and by permission keys
(not granted ⇒ 403). All data church-scoped. Extensible — finance and future modules plug in here.

## 9. Priests & confession calendars

- A church has **priests** (users flagged as priests within that church).
- **Each priest configures his own calendar** — creates/edits his `confession_slot`s (times,
  recurrence, capacity, location). A priest may only edit his own slots (ownership check, not a
  role-name string).
- The **served** may view a priest's open slots and (optionally) book — `confession_booking`.
- All slots/bookings are church-scoped; a priest in Church A is invisible to Church B.
- Localized ar/en, RTL-first; times stored UTC, displayed in church timezone.

## 10. Home-visit schedules

- **Priests and servants** maintain a schedule of **home visits** (`home_visit`): who, where, when,
  status, notes.
- Visible to the assignee and to church admins of the same church; church-scoped.
- Reuse the calendar/`.ics` machinery where useful (the personal calendar feed already exists).

## 11. Financial module (payroll + money-in)

- **Payroll:** `payroll_run` (period, church) → `payroll_line` (user, gross, deductions, net).
- **Money-in:** `money_in` (source, category, amount, date).
- **Money rule (7):** every monetary value is stored as **integer minor units + `currency` +
  `fx_rate`** — never a float. Church-scoped; every create/edit/delete writes to `audit_log` (rule 8).
- Explicitly a **first cut** — reporting, approvals, and reconciliation are extended later (parking lot).

## 12. Parking lot  ← see also root PARKING-LOT.md

Out-of-phase items captured, not built now (root [`PARKING-LOT.md`](../PARKING-LOT.md)): the full
Church-layer request (recorded 2026-07-14); richer finance (reporting/approvals/reconciliation);
Service application richer form builder; `course.service_id` / `church_id` `NOT NULL` contractions
(only in the contract phase); config/security debt.

## 13. Church registration & the polymorphic applications center

- **Church registration panel** (public): a prospective church submits an application/request. It
  lands in the **superadmin** review queue and, on approval, **provisions the church tenant**
  (creates the `church` row + first church-admin membership) — same review UX as course applications.
- **Applications center refactor → polymorphic.** Today applications target a **Course**. Generalize
  the review center to target **Church | Service | Course**, with the subject **type chosen at
  create/edit time**. One queue, one review flow, three subject types (a `subject_type` +
  `subject_id`, or per-type tables with a shared reviewer UI — decided at build time). Church
  applications are superadmin-scoped; service/course applications stay church-admin-scoped.

## 14. Authorization & isolation invariants

- Authorization via **policies + permission keys only** — never hardcoded role-name string checks
  (rule 4). Church-admin, priest, and servant are roles whose *permissions* are the contract.
- **Church admins** manage only their own church; **priests** manage only their own calendar/visits;
  **superadmin** manages churches + approves registrations.
- The **tenant-isolation sacred suite** (`tests/Feature/Tenancy/TenantIsolationTest.php`) must be
  green from T1 on: cross-church read/write must fail; Tenant Zero must "behave exactly as before."

## 15. Structure template anchors  ← CLAUDE.md rule 5

Behavior binds to **structure template anchors**, never to hardcoded level names ("church",
"service", "stage", "class"). A church's product shape (does it have services? courses? exams?
attendance? a management module?) is declared by its **capabilities / structure template**, and code
keys off anchors + capabilities — so a church that is "reporting only" or "servants/served, not
students" needs no code changes.

## 16. Hard rules (mirror of CLAUDE.md — binding)

1. Never break backward compatibility while `MULTI_TENANT=false`.
2. Schema changes are **additive** (expand) only; contractions only in the contract phase, dedicated PRs.
3. Every church-scoped model uses `BelongsToChurch`; bypass only via `->withoutTenancy()` + comment.
4. Authorization via policies + permission keys; no role-name string checks.
5. Behavior binds to structure anchors, not level names.
6. All new strings localized (ar + en); Arabic primary; RTL-first.
7. Money = integer minor units + currency + fx_rate. Never floats.
8. Every destructive action writes to `audit_log`.
9. Tests required per PR; the tenant-isolation suite must pass.
10. Requests exceeding the current phase go to `PARKING-LOT.md` and STOP.

## 17. Open decisions (need product owner input before T0)

1. **Naming** (§3): church-native vs subsidiary-generic vs hybrid. Blocks T0 table names.
2. **Priest & served model:** are "priest" / "servant" / "served" church roles on `user`, or
   separate entities? (Leaning: roles + a thin `priest` link row.)
3. **Confession booking:** do the served *book* slots, or do priests just publish availability?
4. **Church registration provisioning:** auto-create tenant on approval, or superadmin finishes setup?
5. **Finance scope for T6:** currencies in play, payroll cadence, who approves runs.
6. **Timezone per church** (confession/visits) — store on `church.settings`.

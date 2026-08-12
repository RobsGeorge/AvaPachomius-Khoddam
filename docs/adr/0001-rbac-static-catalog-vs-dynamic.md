# ADR-0001 — RBAC: canonical static catalog with a gated dynamic layer

- **Status:** Proposed (draft for review)
- **Date:** 2026-07-26
- **Deciders:** Product owner, engineering lead, QA lead
- **Related:** master-plan T3 (Roles & permissions), CLAUDE.md rule 4, `config/permissions.php`, `app/Services/RoleTemplateService.php`, [`docs/roles-catalog.md`](../roles-catalog.md)

## Context

We questioned whether the **dynamic** roles-and-permissions system is worth keeping, or
whether roles for churches and services should be **static**. Investigating the code shows the
system is already a **three-layer hybrid**, not a fully dynamic one:

1. **Static permission vocabulary** — `config/permissions.php` defines all permission *keys*
   (course / service / system / church / both scopes). This is code-owned and never was dynamic.
2. **Static canonical role templates** — `RoleTemplateService` seeds named default roles
   (`church-admin`, `priest`, `servant`, `service-admin`, `service-member`, `admin`,
   `instructor`, `student`) and clones them into each tenant on provisioning.
3. **Dynamic composition** — the Roles Hub lets a tenant admin create/rename roles and grant
   permission keys.

Authorization is **permission-key based** everywhere (CLAUDE.md rule 4: no role-name string
checks). So "static vs dynamic" only concerns layer 3 — a customization escape hatch on top of
static defaults that already exist.

The felt "inconsistency" comes from exposing two mental models at once (template vs.
tenant-editable role) with no declared canonical set. Docs, onboarding, tests, and our test
plans had to hedge ("delegated/authorized role") because the default catalog was never frozen.

## Decision

1. **The static role catalog is canonical and the default.** [`docs/roles-catalog.md`](../roles-catalog.md)
   is the single source of truth for role names and their permission bundles. UI, onboarding,
   docs, and tests speak this vocabulary.
2. **Authorization stays strictly permission-key based.** No code checks role names. Roles are
   named bundles of keys; the engine is identical whether roles are static or dynamic.
3. **Dynamic role creation/editing is an *advanced capability*, OFF by default per church.**
   The Roles Hub editor is gated behind an explicit capability (leveraging the existing
   `platform.role_templates` / `platform.group_visibility` machinery). Most tenants use the
   static catalog and never see the editor.
4. **Permission vocabulary remains code-owned** in `config/permissions.php` (+ `permissions:sync`).
   Custom roles can only recombine existing keys — never introduce new ones.
5. **We do not remove the dynamic layer now.** Removing shipped capability is a *contraction*
   (master-plan Phase 5 / dedicated PR per hard rules #2 and #10), not an ad-hoc change, and it
   is premature without pilot evidence.

## Why not the alternatives

### A. Rip out the dynamic layer, go fully static
- **Barely simplifies the engine.** Because authz is key-based, "static roles" just means fixed
  role→key maps. The registry, scopes, templates, and policies all remain; only the editing UI
  goes away.
- **Fights the multi-tenant thesis.** Different churches running differently is the core product
  bet; hard-coded maps reintroduce the rigidity T3 removed.
- **It's a live-system contraction** — higher risk, wrong phase.

### B. Leave it fully dynamic (status quo)
- Keeps the exact inconsistency that prompted this ADR: no canonical set, two mental models,
  hedged docs/tests.

The chosen path (canonical static default + gated dynamic escape hatch) gets the consistency of
(A) and the flexibility of (B), and lets us **measure** whether anyone actually uses custom
roles before deciding to retire the layer.

## Consequences

**Positive**
- One role vocabulary across UI, onboarding, docs, tests → the inconsistency disappears.
- Simpler default experience; the Roles Hub complexity is hidden unless explicitly enabled.
- No engine rewrite; CLAUDE.md rule 4 upheld; no contraction risk now.
- We gather real usage data ("did any church create a custom role?") to inform a future retire/keep call.

**Negative / costs**
- Must add/verify a capability gate on the Roles Hub editor and default it off.
- Must align existing UI copy, onboarding, and the test plans to the catalog.
- Churches that already created custom roles need a review/migration path.
- The catalog must be kept in sync with `RoleTemplateService` (add a guard/test).

## Follow-ups (not part of this decision, tracked separately)

- [ ] Gate the Roles Hub editor behind an advanced capability; default OFF. *(new work — schedule per phase)*
- [ ] Reconcile role-slug naming (`admin` → `course-admin`?) as an expand-contract migration if adopted.
- [ ] Fix `servant` `home_visit.manage` → `home_visit.view` if that grant is unintended.
- [ ] Add a test asserting `RoleTemplateService` templates match `docs/roles-catalog.md` (drift guard).
- [ ] **PARKING-LOT:** "Retire dynamic role editing?" — revisit after the staging pilot with
      usage data on custom-role creation.
- [ ] Document capability → permission-key mappings that feed `church-admin` at clone time.

## Review checklist before accepting

- [ ] Catalog permission sets verified against current `RoleTemplateService` + `permissions:sync`.
- [ ] Product owner confirms the default catalog matches how churches actually operate.
- [ ] Engineering confirms the capability gate is feasible without touching the authz engine.

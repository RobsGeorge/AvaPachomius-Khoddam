# P6 — Pilot: onboard subsidiary #2

**Goal:** prove the whole model end-to-end by onboarding a real second subsidiary whose product is
**deliberately different** from the Academy — ideally a *Service* (خدمة) tenant with `servant`/
`served` roles, no exams, and lenient or no attendance, **or** a reporting-only tenant.

**Prereq:** P1–P5.

---

## 1. Pick a contrasting pilot

Choose one that exercises the differentiators so gaps surface:

| Pilot type | Capabilities | Roles | Proves |
|---|---|---|---|
| **Service / Sunday-school** | attendance (lenient, no penalty), reporting; **no exams, no grades** | `admin`, `servant/خادم`, `served/مخدوم` (no *student*) | custom roles, per-sub attendance rules, capability gating |
| **Reporting-only** | reporting only | `admin`, `reporter` | no-course/no-module subsidiary, subsidiary-wide grants |
| **Recurring-years** | curriculum (`modules=false`, `recurring_years=true`) | `admin`, `member` | years without modules |

## 2. Onboarding runbook

1. Superadmin → Console → **Create Subsidiary** (`slug=service`, name, branding).
2. Enable only the intended capabilities + their config (e.g. `attendance.mode=lenient,
   penalty=false`).
3. `RoleTemplateService` clones admin/member; then create the **custom roles** `servant`, `served`
   and set their permission matrix (e.g. servant → `attendance.record`, `report.view`;
   served → `attendance.view_own`).
4. Invite/link the subsidiary admins; they invite the rest.
5. Verify `service.inst.org` is live (no DNS/cert step needed under the P4 wildcard).

## 3. Validation checklist (the acceptance of the whole project)

- **Isolation:** Service admin cannot see Academy data via any route, search, or direct ID access;
  attempts to write into Academy fail. Run the cross-tenant read/write tests against real data.
- **Capability gating:** exam/grade routes 404 on `service.inst.org`; nav hides them; attendance
  uses lenient config.
- **Custom roles:** `servant`/`served` work with zero code changes; permission checks pass/deny
  correctly.
- **Cross-subsidiary identity:** a person who is a *member* of both is a `student` in Academy and a
  `servant` in Service; SSO logs them into both; each subdomain shows the right role/UI.
- **Branding:** Service subdomain shows its own logo/theme/locale.
- **Provisioning self-service:** the entire onboarding above was done from the UI, no SQL/deploy.
- **Audit:** provisioning, capability/role/grant changes recorded and filterable by subsidiary.
- **Force-logout / sessions:** flush-all and force-logout work across both subdomains (DB sessions).

## 4. Operational

- **Rollback plan:** suspend the subsidiary (`status=suspended` → 404) without deleting data;
  archive when retiring. Document data-retention/export expectations.
- **Performance:** confirm `subsidiary_id` indexes are used (EXPLAIN on the hot queries —
  attendance, grades, curriculum) now that every query carries the scope.
- **Monitoring:** watch for any query missing the global scope (a child table queried directly
  without the trait) — add to `tenant_tables` + trait if found.
- **Security review:** run the repo's `/security-review` over the tenancy + permission code before
  declaring GA.

## 5. After the pilot (GA)

- Onboard remaining subsidiaries from the console.
- Optional: custom domains for subsidiaries that want independent identity (per-domain DNS+TLS).
- Optional later phases: per-subsidiary data export/import, billing/quotas, cross-subsidiary
  consolidated reporting for central institution oversight (a superadmin "view across" mode using
  `withoutGlobalScope`).

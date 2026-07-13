# CLAUDE.md — AvaPachomius-Khoddam / Khedma Platform

## What this is
Laravel app LIVE in production at avapakhomios.com serving real church servants.
Being migrated in-place to a multi-tenant church platform ("Khedma", working title)
via expand-contract. AvaPachomius = Tenant Zero (church_id=1). Its current features
are being extracted into the "servants-prep" service module.

Full spec: docs/khedma-master-plan.md — READ IT before any structural work.
Current phase: see master-plan §7. Do not build ahead of the current phase.

## Hard rules
1. NEVER break backward compatibility while MULTI_TENANT=false.
2. Schema changes are ADDITIVE only (expand). Contractions happen only in Phase 5,
   in dedicated PRs. Never drop/rename a column outside that.
3. Every tenant-scoped model uses the BelongsToChurch trait (global scope). Never
   write a query bypassing it without an explicit ->withoutTenancy() and a comment
   justifying it.
4. No hardcoded role-name string checks in controllers. Authorization via Policies +
   permission keys only.
5. No level names ("stage", "class") hardcoded in code — behavior binds to structure
   template anchors (master-plan §15).
6. All new strings localized (ar + en). Arabic is primary. UI is RTL-first.
7. Money = integer minor units + currency + fx_rate. Never floats.
8. Every destructive action writes to audit_log.
9. Tests required per PR. The tenant isolation suite must pass.
10. If a request exceeds the current phase, append it to PARKING-LOT.md and STOP.

## Environment
- PHP 8.2 (note: box also has 8.4/8.5 installed; CLI must be pinned to 8.2), Laravel,
  MySQL 8, Nginx.
- Production: /var/www/avapakhomios, MULTI_TENANT=false, php8.2-fpm.sock
- Staging:    /var/www/khedma-staging, MULTI_TENANT=true, php8.2-fpm-staging.sock
- Deploy: /var/www/deploy.sh [production|staging]
- No frontend build step (no package.json). Do not add npm steps to CI/deploy.

## Key paths (as they come to exist)
- docs/khedma-master-plan.md — source of truth
- app/Tenancy/ — TenantContext, ResolveTenant middleware, BelongsToChurch trait
- tests/Feature/Tenancy/IsolationTest.php — the sacred suite
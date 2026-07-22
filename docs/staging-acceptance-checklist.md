# Staging acceptance checklist — T7 + T8

One-page runbook for **staging sign-off** before production `MULTI_TENANT=true`.
See also: [`tenancy-cutover.md`](tenancy-cutover.md), [`architecture/multi-subsidiary/P6-pilot.md`](architecture/multi-subsidiary/P6-pilot.md).

**Automated gate (run on the server after deploy):**

```bash
cd /var/www/khedma-staging
php8.2 artisan migrate:deploy --force
php8.2 artisan tenancy:acceptance-check --expect-multi-tenant --repair-orgs
php8.2 vendor/bin/phpunit tests/Feature/Tenancy tests/Feature/Structure
```

---

## Part A — T7 tenancy cutover

### A1. Deploy & schema

| Step | Command / action | Pass |
|------|------------------|------|
| Deploy | `/var/www/deploy.sh staging` or CI deploy | Latest `staging` on server |
| Migrate | `php8.2 artisan migrate:deploy --force` | Exit 0 |
| Acceptance script | `php8.2 artisan tenancy:acceptance-check --t7 --repair-orgs` | No **FAIL** lines |

### A2. Infra & `.env` (staging only)

Set **before** expecting subdomain isolation:

```env
MULTI_TENANT=true
TENANCY_BASE_DOMAIN=<your-staging-apex>
TENANCY_CONSOLE_HOST=admin.<your-staging-apex>
SESSION_DRIVER=database
```

| Step | Pass |
|------|------|
| Wildcard DNS `*.<TENANCY_BASE_DOMAIN>` → staging | Resolves |
| TLS valid on apex + wildcard | Browser shows lock |
| `php artisan config:cache` after env change | No stale config |

### A3. Pilot church

```bash
php8.2 artisan tenancy:seed-pilot-church pilot-service \
  --name="Pilot Service Church" \
  --admin=<your-email@example.com>
```

Re-run is safe (idempotent; repairs missing `organizations` link).

### A4. Manual smoke (P6 §3)

| # | Check | Pass |
|---|--------|------|
| 1 | Main tenant URL (Tenant Zero slug / apex) | Login, existing data unchanged |
| 2 | `https://pilot-service.<base>/` | Loads; pilot branding/context |
| 3 | `https://admin.<base>/` | Superadmin console |
| 4 | Pilot admin **cannot** open Tenant Zero course/user by ID | 404 / empty / denied |
| 5 | Tenant Zero admin **cannot** see pilot data | Same |
| 6 | Pilot nav: **no** exams/grades (capability gating) | Routes 404 or hidden |
| 7 | Pilot attendance works (lenient config if set) | Record/view OK |
| 8 | User not in church rejected on that host | Membership gate |
| 9 | Church switcher host links | Correct tenant per link |
| 10 | Same user in both churches | Correct role/UI per subdomain |
| 11 | Suspend pilot (`status=suspended`) | 404 on pilot host |
| 12 | Audit log | Provisioning / capability changes visible |

### A5. Automated tests (CI or local)

```bash
php8.2 vendor/bin/phpunit tests/Feature/Tenancy/TenantIsolationTest.php
php8.2 vendor/bin/phpunit tests/Feature/Tenancy/IsolationTest.php
php8.2 vendor/bin/phpunit tests/Feature/Tenancy/TenancyCutoverTest.php
php8.2 vendor/bin/phpunit tests/Feature/Tenancy/StagingAcceptanceCheckTest.php
```

### T7 sign-off

- [ ] `tenancy:acceptance-check --t7 --expect-multi-tenant --repair-orgs` passes on staging
- [ ] P6 manual table (A4) checked
- [ ] Sacred isolation suite green
- [ ] Product owner approves (**production stays `MULTI_TENANT=false` until this**)

---

## Part B — T8 structure expand (T8a + T8b)

Run **after** T7 migrations are on staging (can parallel with T7 manual smoke).

### B1. Deploy & automated gate

```bash
php8.2 artisan migrate:deploy --force
php8.2 artisan tenancy:acceptance-check --t8
php8.2 vendor/bin/phpunit tests/Feature/Structure
```

| Automated check | Pass |
|-----------------|------|
| `structure_templates` seeded (3 keys) | `educational_standard`, `meeting_flat`, `care_sector` |
| Default service `slug = servants-prep` | Bound to `educational_standard` |
| `service_units` | Rows exist if courses linked to default service |
| `enrollments` | Table exists; count ≥ `user_course_role` after backfill |
| `attendance.lock_version` | Column exists |

### B2. Manual URL smoke

| URL / action | Expected |
|--------------|----------|
| `GET /services/{numeric-id}/apply` | **301** → `/services/servants-prep/apply` |
| `GET /s/servants-prep` | Redirect to service hub; session `current_service_id` set |
| `route()` for `{service}` | Slug in URL, not numeric id |
| Assign course role | New `enrollments` row mirrors `user_course_role` |
| Attendance update with stale `lock_version` | **409** conflict |
| Service with template missing attendance anchor | Attendance nav links hidden |

### B3. T8 residual (not required for sign-off)

Parked in `PARKING-LOT.md` — do **not** block T7/T8 acceptance on these:

- Contract: drop `user_course_role` (reads still on UCR)
- Full `/{service:slug}/…` route tree
- Church-admin template picker on service create
- Nav driven purely from structure template

### T8 sign-off

- [ ] `tenancy:acceptance-check --t8` passes on staging
- [ ] Structure test suite green
- [ ] Manual URL table (B2) spot-checked

---

## Rollback

| Action | Effect |
|--------|--------|
| Suspend pilot church | Pilot host 404 |
| `MULTI_TENANT=false` on staging | Dormant Tenant Zero binding (scopes → church 1) |
| Do **not** drop `NOT NULL church_id` in prod | Requires dedicated contraction PR |

---

## Sign-off log (fill in)

| Field | Value |
|-------|--------|
| Date | |
| Staging deploy SHA | |
| T7 approved by | |
| T8 approved by | |
| Notes | |

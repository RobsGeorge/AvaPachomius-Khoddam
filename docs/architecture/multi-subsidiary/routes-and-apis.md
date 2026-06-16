# Routes & APIs — Exact Description

This app is **web-first (Blade)**; the API surface is minimal (`routes/api.php` only exposes
`GET /api/user` via Sanctum). All new functionality below is **web routes** unless marked API.

## Host model

| Host | Purpose | Tenant binding | Guard |
|---|---|---|---|
| `<domain>` (apex) | landing / subsidiary chooser | first subsidiary (fallback) | public |
| `{slug}.<domain>` | a subsidiary's portal | resolved by slug/`domain` | `auth` + `subsidiary.member` |
| `{custom-domain}` | a subsidiary on its own domain | resolved by `subsidiary.domain` | same |
| `admin.<domain>` (console host) | umbrella superadmin console | **none** (cross-tenant) | `auth` + `superadmin` |

## Middleware

### Aliases to register in `app/Http/Kernel.php`
```php
'subsidiary.member' => \App\Http\Middleware\EnsureSubsidiaryMember::class,  // P1
'capability'        => \App\Http\Middleware\RequireCapability::class,        // P2
'permission'        => \App\Http\Middleware\RequirePermission::class,        // P3
```
`RoleMiddleware` (`role`) is **retired** in P3 (kept only until all routes migrate).

### Global `web` group order (after StartSession, before LogUserActivity)
```
… StartSession … SetLocale → IdentifySubsidiary (P1) → LogUserActivity
```
`IdentifySubsidiary` runs for every web request and binds `currentSubsidiary` (or none on the
console host).

### Per-route stack pattern (P2/P3)
```php
Route::middleware(['auth','subsidiary.member','capability:<cap>','permission:<perm>'])->group(...);
```
Order matters: membership (are you in this tenant?) → capability (does this tenant have the
feature? → 404) → permission (may you? → 403).

## Existing-route changes (P2 + P3)

Wrap current groups in `routes/web.php` with `capability:` and translate `role:` → `permission:`:

| Existing | Add capability | Replace role check |
|---|---|---|
| exam routes (`exams.*`) | `capability:exams` | `role:instructor,admin` → `permission:exam.author`/`exam.grade`/… per route |
| attendance staff routes | `capability:attendance` | `attendance.staff` mw → `permission:attendance.record` |
| attendance reports (`role:admin,instructor`) | `capability:attendance` | `permission:attendance.view_all` |
| modules/curriculum/lectures (`role:admin,instructor`) | `capability:curriculum` | `permission:curriculum.manage`/`module.manage` |
| assignments dashboard/CRUD (`role:instructor,admin`) | `capability:assignments` | `permission:assignment.manage`/`assignment.grade` |
| grades management (`role:admin,instructor`) | `capability:grades` | `permission:grade.manage` |
| `admin` group (translations, graduation-settings, approvals) | — | `permission:user.approve` / `subsidiary.configure` as appropriate |
| `superadmin` group | — | **unchanged** |

Student/member read routes (`curriculum.show`, `grades.show`, `attendance.my`, `assignments.submit`,
`exams.attempt.*`) get `subsidiary.member` + the relevant `capability:`; permissions use the
`*.view_own` / `*.take` / `*.submit` keys.

## New routes — Console (superadmin, host = `admin.<domain>`)

Controllers under `App\Http\Controllers\Console\`. Group:
```php
Route::domain(config('tenancy.console_host'))->middleware(['auth','superadmin'])
    ->prefix('console')->name('console.')->group(function () { … });
```

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/subsidiaries` | `console.subsidiaries.index` | list all subsidiaries |
| GET | `/subsidiaries/create` | `console.subsidiaries.create` | create wizard |
| POST | `/subsidiaries` | `console.subsidiaries.store` | → `TenantProvisioningService::create()` |
| GET | `/subsidiaries/{subsidiary}` | `console.subsidiaries.show` | overview |
| GET | `/subsidiaries/{subsidiary}/edit` | `console.subsidiaries.edit` | identity/branding |
| PUT | `/subsidiaries/{subsidiary}` | `console.subsidiaries.update` | save |
| POST | `/subsidiaries/{subsidiary}/suspend` | `console.subsidiaries.suspend` | status=suspended |
| POST | `/subsidiaries/{subsidiary}/archive` | `console.subsidiaries.archive` | status=archived |
| PUT | `/subsidiaries/{subsidiary}/capabilities` | `console.capabilities.update` | toggle + config |
| GET | `/subsidiaries/{subsidiary}/members` | `console.members.index` | members |
| POST | `/subsidiaries/{subsidiary}/members` | `console.members.store` | invite/add |
| PUT | `/subsidiaries/{subsidiary}/members/{user}` | `console.members.update` | role/status |
| DELETE | `/subsidiaries/{subsidiary}/members/{user}` | `console.members.destroy` | remove |
| GET | `/subsidiaries/{subsidiary}/roles` | `console.roles.index` | role × permission matrix |
| POST | `/subsidiaries/{subsidiary}/roles` | `console.roles.store` | create role |
| PUT | `/subsidiaries/{subsidiary}/roles/{role}` | `console.roles.update` | rename + grants |
| DELETE | `/subsidiaries/{subsidiary}/roles/{role}` | `console.roles.destroy` | delete (guarded) |
| GET | `/audit` | `console.audit.index` | audit, filter by subsidiary |

Existing `/superadmin/*` routes are moved/aliased under the console host (impersonation,
flush-all-sessions stay).

## New routes — Subsidiary self-service (in-subdomain, scoped)

Controllers under `App\Http\Controllers\Manage\`; same screens as console minus cross-tenant + minus
capability *enablement* (config only). Target subsidiary = `Tenancy::current()`.
```php
Route::middleware(['auth','subsidiary.member','permission:role.manage'])
    ->prefix('manage')->name('manage.')->group(function () {
        // manage.members.*, manage.roles.*, manage.capabilities.update (config only),
        // manage.branding.edit/update  — all bound to current subsidiary, behind RolePermissionPolicy
    });
```

## New routes — auth / membership (P4)

| Method | URI | Name | Purpose |
|---|---|---|---|
| GET | `/switch` | `subsidiary.switch` | list `auth()->user()->subsidiaries`, link each subdomain |
| (reuse) | `/set-password/{user_id}` | `password.set` | invited-user onboarding (existing flow) |

## APIs

Keep the surface minimal. **If** a JSON API is later needed for provisioning/automation, expose
under `routes/api.php` with `auth:sanctum` + an ability check, mirroring the console actions:

| Method | URI | Ability | Returns |
|---|---|---|---|
| GET | `/api/subsidiaries` | superadmin token | list |
| POST | `/api/subsidiaries` | superadmin token | provision (same service) |
| GET | `/api/me/subsidiaries` | any token | caller's memberships |

Default recommendation: **do not build the API in P0–P6** unless a concrete consumer exists; the
web console covers all flows.

## Naming conventions

- Route names: `console.*` (superadmin host), `manage.*` (subsidiary self-service), existing names
  unchanged elsewhere.
- Permissions: `capability.action` (`exam.grade`); platform perms have no capability prefix
  (`subsidiary.configure`).
- Always use `route()` helpers — host is inferred from the current request, so subdomains need no
  URL rewriting.

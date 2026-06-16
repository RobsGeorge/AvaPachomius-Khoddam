# P4 — Subdomains live

**Goal:** turn on real subdomain resolution, cross-subdomain SSO, and the superadmin console host.
After this phase, `clinic.inst.org` resolves to the Clinic subsidiary and one login spans all
subdomains.

**In scope:** wildcard DNS/TLS, `TrustHosts`, `SESSION_DOMAIN`, session driver `file → database`,
console host routing, real slug/custom-domain resolution, local-dev host setup.
**Prereq:** P1 (resolution + membership gate), and ideally P2/P3 so a new subdomain is useful.

---

## 1. Infrastructure (one-time)

- **Wildcard DNS:** `*.inst.org → server IP` (one record). After this, *any* slug resolves to the
  app — new subsidiaries need no DNS change.
- **Wildcard TLS:** one cert for `*.inst.org` via Let's Encrypt **DNS-01** challenge (HTTP-01 can't
  do wildcards), auto-renewing. Covers every current and future subdomain.
- **Web server:** single vhost/server block matching `*.inst.org` and `admin.inst.org` → same app
  root. No per-subsidiary server config.

> Result: provisioning a subsidiary (P5) is a single DB insert; the subdomain is live on the next
> request, no infra step. The one exception is **custom domains** (`clinic-care.com`), which still
> need a per-domain DNS record + cert — not self-service.

## 2. Enable `TrustHosts`

Uncomment in `app/Http/Kernel.php` global middleware:
```php
protected $middleware = [
    \App\Http\Middleware\TrustHosts::class,   // ← enable
    \App\Http\Middleware\TrustProxies::class,
    ...
];
```
`TrustHosts::hosts()` already returns `allSubdomainsOfApplicationUrl()`. Set `APP_URL=https://inst.org`.

## 3. Session: cross-subdomain SSO + correctness

```dotenv
SESSION_DRIVER=database          # was 'file' — REQUIRED for cross-subdomain force-logout/audit
SESSION_DOMAIN=.inst.org         # leading dot → cookie shared across all subdomains
SESSION_SECURE_COOKIE=true
APP_URL=https://inst.org
```

- Add the `sessions` table migration (`php artisan session:table`) — note the **non-standard PK
  conventions** in this app; create it with the project's idempotent guard pattern.
- **Why DB sessions are mandatory here:** `ForceLogoutService` / "flush all sessions" and the audit
  features must see sessions across subdomains. The `file` driver + shared cookie domain works for
  login but breaks server-side session enumeration/invalidation. Verify `ForceLogoutService` after
  the switch.
- `.inst.org` cookie = a user authenticated on `academy.inst.org` is authenticated on
  `service.inst.org` too — but the **membership gate (P1)** still blocks access if they aren't a
  member there. SSO ≠ authorization.

## 4. Real resolution

`IdentifySubsidiary` (built in P1) already resolves `domain` → `slug` → `main`. In P4 it now
matches actual subdomains because DNS/TLS deliver them. No code change beyond ensuring the
`main`/apex fallback still serves the apex/landing as intended.

## 5. Console host

```php
// routes/web.php — superadmin console on its own host, no tenant binding
Route::domain(config('tenancy.console_host'))->middleware(['auth','superadmin'])->group(function () {
    // existing /superadmin routes move/alias here; cross-tenant (global scope off)
});
```
`IdentifySubsidiary` returns early for `console_host` (no `Tenancy::set`), so the global scope is
inactive there and superadmin sees all subsidiaries. Keep the in-subdomain `/superadmin` behavior
too if desired (scoped to that subsidiary).

## 6. Login flow across subdomains

`LoginController` after successful auth:
- If not a member of the resolved subsidiary and not superadmin → reject with
  `auth.not_a_member` (a "request access / switch subsidiary" message).
- Optional "subsidiary switcher": list `auth()->user()->subsidiaries` and link to each subdomain.

## 7. Local development

- `*.inst.test` → `127.0.0.1`: dnsmasq (`address=/inst.test/127.0.0.1`) or per-slug hosts entries
  (`academy.inst.test`, `service.inst.test`, `admin.inst.test`).
- `.env`: `APP_URL=http://inst.test`, `SESSION_DOMAIN=.inst.test`, `TENANCY_CONSOLE_HOST=admin.inst.test`.
- Update `.env.example` and `.github/DEPLOY-VPS.md` with the wildcard DNS/TLS + session changes.

## Acceptance criteria

- `clinic.inst.org` resolves to Clinic; `academy.inst.org` to Academy; apex → main/landing.
- Login on one subdomain authenticates the others (SSO), but membership gate still enforces access.
- `admin.inst.org` serves the superadmin console with cross-tenant visibility.
- `ForceLogoutService` / flush-all-sessions works across subdomains (DB sessions verified).
- A brand-new subsidiary row is reachable at its subdomain with **no** DNS/cert/deploy step.

# Demo / staging seed data

A one-command, fully reversible dataset for **staging or local** testing: churches (tenants)
with services, priests, confession slots, courses (instructors, students, sessions,
assignments, announcements), home visits, finance records, families, events, and the full
role/membership graph — every account log-in-able with a known password.

It is **not** for production. The commands refuse to run there.

---

## What gets created

Two churches (tenants), each provisioned exactly like the SuperAdmin console does it
(`ChurchProvisioningService`) — organization row, **all capabilities enabled**, and cloned
role templates (`church-admin` / `priest` / `servant`):

| Church | Slug | Highlights |
|---|---|---|
| St Mark Church (Demo) | `demo-stmark` | 2 priests + confession slots, 2 services (Sunday School, Youth Meeting), a course with 1 instructor + 5 students + sessions + assignment + announcement, a home visit, a finance record, a family, an event |
| St George Church (Demo) | `demo-stgeorge` | 1 priest + slot, 1 service, a course with 1 instructor + 3 students, a home visit + finance record |

Plus one **platform superadmin** who can reach the console host.

**Everything is tagged** so it can be removed exactly, without touching real data:
- churches → slug starts with `demo-` (and `settings.demo = true`)
- users → email ends with `@demo.khedma.test`
- all other rows are church-scoped under a demo church

### Credentials

Every account shares the password **`Demo1234!`** (override with `DEMO_SEED_PASSWORD`).
The seed command prints the full table and writes a copy to
`storage/app/demo-credentials.md`. Representative accounts:

| Role | Email |
|---|---|
| Platform superadmin | `superadmin@demo.khedma.test` |
| St Mark church admin | `admin.stmark@demo.khedma.test` |
| St Mark priest | `priest1.stmark@demo.khedma.test` |
| St Mark service admin | `service-admin.stmark@demo.khedma.test` |
| St Mark instructor | `instructor.stmark@demo.khedma.test` |
| St Mark student | `student1.stmark@demo.khedma.test` … `student5.stmark@…` |
| St George church admin | `admin.stgeorge@demo.khedma.test` |

---

## Running it on staging (exact steps)

1. **Enable it** in the staging `.env` (only there):
   ```
   DEMO_SEED_ENABLED=true
   # optional: DEMO_SEED_PASSWORD=YourChoice
   ```
2. **Refresh config** (staging caches config):
   ```
   php artisan config:clear      # or: php artisan config:cache
   ```
3. **Seed**:
   ```
   php artisan demo:seed
   # non-interactive / scripted:
   php artisan demo:seed --force
   # to wipe any previous demo data first:
   php artisan demo:seed --fresh
   ```
   The command prints the credentials table and the login URLs, and writes
   `storage/app/demo-credentials.md`.

### To actually log in at a church subdomain

The portals live at `https://<slug>.<base-domain>` (e.g.
`https://demo-stmark.staging.avapakhomios.com`). For that to resolve you need the wildcard
you already set up:
- **DNS**: a wildcard `*.staging.avapakhomios.com` A record → the VPS.
- **TLS**: a wildcard certificate for `*.staging.avapakhomios.com` (the churches resolve by
  subdomain via `ResolveTenant`, no per-church nginx change needed).

The superadmin console is at `https://<TENANCY_CONSOLE_HOST>` (see the staging `.env`).

---

## Removing it

By marker only — real data is never touched:

```
php artisan demo:wipe            # confirms first
php artisan demo:wipe --force    # no prompt
```

It deletes every row scoped to a `demo-` church, every `@demo.khedma.test` user (and their
tokens), and the demo churches/organizations, with FK checks disabled so order is irrelevant.
It prints a per-scope deletion count.

---

## Is it worth removing later?

**On a dedicated staging/testing box: usually no — keep it.** It's the whole point of the
environment, and re-seeding costs nothing. Reasons you *would* wipe:

- **Reset to a clean slate** between big test cycles, or before a demo to stakeholders →
  `demo:wipe` then `demo:seed --fresh`.
- **Staging is periodically refreshed from a production dump** → the demo data won't be in the
  dump; just re-run `demo:seed` after each refresh.
- **It's getting in the way** of a specific real-data test scenario.

Because everything is namespaced (`demo-` / `@demo.khedma.test`), the demo data never collides
with real staging data, so leaving it in place is safe. Treat `demo:wipe` as a convenience, not
a cleanup obligation.

> Never enable `DEMO_SEED_ENABLED` on production. Even if it were set, `demo:seed` refuses to
> run when `APP_ENV=production` (short of an explicit `--force`, which you should not use there).

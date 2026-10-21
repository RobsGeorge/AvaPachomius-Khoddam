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
| St Mark Church (Demo) | `demo-stmark` | 2 priests + confession slots, 3 services (**Servants Preparation** `servants-prep` on the educational template, Sunday School, Youth Meeting), the servants-prep course below, a home visit, a finance record, a family, an event |
| St George Church (Demo) | `demo-stgeorge` | 1 priest + slot, 1 service, a course with 1 instructor + 3 students, a home visit + finance record |

### The St Mark servants-prep course (mirrors the live Tenant Zero course)

"إعداد الخدام — السنة الأولى" (Servants Preparation — Year 1), built by
`database/seeders/Demo/ServantsPrepDemoCourse.php`. All dates are relative to the day you seed:

- **Roster:** 1 course admin, 2 instructors, 20 students (Arabic names).
- **Curriculum:** 3 modules × 4 weekly sessions (19:00), one lecture + material each.
  Modules 1–2 ended, module 3 active. Weeks 1–9 are in the past, 10–12 upcoming.
- **Attendance:** weeks 1–8 closed through `AttendanceCloseService` (present / late / permission /
  absent, attendance gradebook lines synced). Week 9 is recorded but **left open** so staff can demo
  closing it.
- **Quizzes:** module 1 quiz graded and announced; module 2 quiz graded but **not announced** (demo
  "announce results"); final exam published and scheduled for week 12.
- **Assignments:** one graded with feedback, one submitted and **awaiting grading**, one upcoming.
- **Feedback surveys:** module 1 closed with responses; module 2 open — `student1` has **not**
  answered it yet, so the student view can fill it in.
- **Gradebook:** the work graded so far: attendance 40%, quizzes 60%, so the report shows a
  realistic pass/fail spread mid-term. Staff add final-exam and project categories (and
  rebalance) when that work is graded.
- **Project:** published 3-team field-ministry project; joining is open, so students can pick a team.
- **Announcements:** three course announcements.

### Which account shows which view

| View | Log in as | Where |
|---|---|---|
| Platform superadmin | `superadmin@…` | console host (`TENANCY_CONSOLE_HOST`) — sees **every** church on the box |
| Church "superadmin" (owner of one church) | `admin.stmark@…` | `demo-stmark` portal |
| Service admin (Servants Preparation) | `service-admin.stmark@…` | `demo-stmark` portal |
| Course admin | `course-admin.stmark@…` | `demo-stmark` portal |
| Instructor | `instructor.stmark@…` (modules 1–2), `instructor2.stmark@…` (module 3, owns the open week-9 roll) | `demo-stmark` portal |
| Student | `student1.stmark@…` … `student20.stmark@…` | `demo-stmark` portal |

For an external client, prefer `admin.stmark` as their "superadmin": the platform superadmin can see
and impersonate users in every church on the server, including real ones.

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
| St Mark service admin (Servants Preparation) | `service-admin.stmark@demo.khedma.test` |
| St Mark course admin | `course-admin.stmark@demo.khedma.test` |
| St Mark instructors | `instructor.stmark@demo.khedma.test`, `instructor2.stmark@demo.khedma.test` |
| St Mark students | `student1.stmark@demo.khedma.test` … `student20.stmark@…` |
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
tokens and people-registry rows), child rows reachable only through a demo parent (exam
questions/options/answers, survey questions/answers, curriculum pivots, role grants, family
members), and the demo churches/organizations, with FK checks disabled so order is irrelevant.
It prints a per-scope deletion count and records a `demo.wiped` audit event.

### Refreshing an older demo (e.g. one seeded before the servants-prep course existed)

```
php8.2 artisan demo:seed --fresh --force
```

Re-seed shortly before a client demo: dates are relative to the seed day, so "last week's"
session and the open survey stay current.

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
